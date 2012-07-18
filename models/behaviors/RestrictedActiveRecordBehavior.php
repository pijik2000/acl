<?php

Yii::import('acl.models.behaviors.*');
/**
 * RestrictedActiveRecordBehavior Class File
 * This class serves as a behavior for all the objects which have to control 
 * their access
 *
 * @author dispy <dispyfree@googlemail.com>
 * @license LGPLv2
 * @package acl.base
 */

/**
 * This class is intended tobe used as a behavior for objects which have restrictions on their access
 * It automatically checks, if the current user has the permissions to commit the regular CRUD-tasks
 */
class RestrictedActiveRecordBehavior extends AclObjectBehavior{
    
    /**
     * Overwrite this method to return the actual class Name
     * @return  either "Aro" or "Aco"
     */
    protected function getType(){
        return 'Aco';
    }

    /**
     * The following functions generates the CDbCriteria necessary to filter all accessable rows
     * The CDbCriteria is solely passsed to the wrapped methods
     * @param sql $conditions the conditions being passed to the real method
     * @param array $params the params being passed to the real method
     * @param   array   $options    options to be used by the method itself (keys: disableInheritance)
     * @return CDbCriteria the criteria assuring that the user only gets what he has access to
     */
    protected function generateAccessCheck($conditions = '', $params = array(), $options = array()){
        if(is_object($conditions) && get_class($conditions) == 'CDbCriteria'){
            $criteria = $conditions;
        }
        else{
            $criteria = new CDbCriteria;
            $criteria->mergeWith(array(
                'condition' => $conditions,
                'params'    => $params
            ));
        }
        
        //If he's generally allowed, don't filter at all
        if(RestrictedActiveRecord::mayGenerally(get_class($this->getOwner()), 'read'))
                return $criteria;
   
        $options = array_merge(RestrictedActiveRecord::$defaultOptions, $options);
        
        //If the check is bypassed, return criteria without check
        if(RestrictedActiveRecord::$byPassCheck)
            return $criteria;
        
        $criteria->distinct = true; //Important: there can be multiple locations which grant permission
            
            //Inner join to get the collection associated with this content
            $acoClass = Strategy::getClass('Aco');
            $collection = 'INNER JOIN `'.$acoClass::model()->tableName().'` AS acoC ON acoC.model = :RAR_model AND acoC.foreign_key = t.id';
                $criteria->params[':RAR_model'] = get_class($this->getOwner());
            
            //Inner join to the associated aco-nodes themselves to get the positions
            $acoNodeClass = Strategy::getClass('AcoNode');
            $nodes = ' INNER JOIN `'.$acoNodeClass::model()->tableName().'` AS aco ON aco.collection_id = acoC.id';
            
            //But before: fetch the positions of the current user
            $aroClass = Strategy::getClass('Aro');
            $user = RestrictedActiveRecord::getUser();
            $aro = $aroClass::model()->find('model = :model AND foreign_key = :foreign_key', 
                    array(':model'=> RestrictedActiveRecord::$model, ':foreign_key' => $user->id));
            
            //If we are nobody... we are a guest^^
            $guest = Strategy::get('guestGroup');
            if(!$aro && $guest){
                 $aro = $aroClass::model()->find('alias = :alias', 
                    array(':alias' => $guest));
                 
                 //If there's no guest group... we are nobody and we may nothing ;)
                 if(!$aro)
                     return array();
            }
               
            
            $aroPositions = $aro->fetchComprisedPositions();
            $aroPositionCheck = $aro->addPositionCheck($aroPositions, "aro", "map"); 
            
            //Get our action :)
            $action = Action::model()->find('name = :name', array(':name' => 'read'));
            
            if($action === NULL)
                throw new RuntimeException('Unable to find action read');
            
            //Now, join connecting table
            $acoCondition = $acoClass::buildTreeQueryCondition(
                    array('table' => 'aco'),
                    array('table' => 'map', 'field' => 'aco'),
                    $options['disableInheritance']
                    );
            $connection = ' INNER JOIN `'.Permission::model()->tableName().'` AS map ON '.$acoCondition.' AND '.$aroPositionCheck.' AND map.action_id = :acl_action_id';
            $criteria->params[':acl_action_id'] = $action->id;
        
       $joins = array($collection, $nodes, $connection);
       
       foreach($joins as $join){
           $criteria->mergeWith(array('join' => $join), true);
       }
       
       return $criteria;
    }
    
    public function beforeFind(&$event){
        parent::beforeFind($event);

        
        $this->owner->dbCriteria =  $this->generateAccessCheck();
    }
    
    
    /**
     * Gets the Aros who are directly (no inheritance!) permitted to perform
     * one of the specified actions on this object
     * @param mixed $actions the actions to be considered
     * @return array All of the objects which have one of the permissions
     */
    public function getDirectlyPermitted($actions = '*'){
        //First, fetch all of the action Ids
        $owner = $this->getOwner();
        $actions = Action::translateActions($owner, $actions);
        $actionCondition = Util::generateInStatement($actions);
        $actions = Action::model()->findAll('name '.$actionCondition);
        
        $actionIds = array();
        foreach($actions as $action){
            $actionIds[] = $action->id;
        }
        $actionIdCondition = Util::generateInStatement($actionIds);
        
        //Get the associated Aco first
        $aco = AclObject::loadObjectStatic($owner, 'Aco');
        //Fetch all of the own positions and build condition
        $positions = $aco->fetchComprisedPositions();
        $acoCondition = Util::generateInStatement($positions);
        
        $aroNodeClass   = Strategy::getClass('AroNode');
        
        $rGroupTable    = RGroup::model()->tableName();
        $nodeTable      = $aroNodeClass::model()->tableName();
        $permTable      = Permission::model()->tableName();
        return Yii::app()->db->createCommand()
                ->selectDistinct('t.id AS collection_id, t.foreign_key, t.model, t.alias, p.action_id')
                ->from($rGroupTable.' t')
                ->join($nodeTable.' n', 'n.collection_id = t.id')
                ->join($permTable.' p', 
                        'p.aro_id = n.id AND p.aco_path '.$acoCondition.' AND p.action_id '. $actionIdCondition)
                ->queryAll()
                ;
    }
    
    /**
     * This method checks whether the user has the right to update the current record
     * By default, it's always allowed to create a new object. This object is automatically assigned to the user who created it with full permissions
     */
    public function beforeSave($event){
        //The Record is updated
        $aro = RestrictedActiveRecord::getUser();
        
        //If there's no aro, don't assign any rights
        if($aro === NULL)
            return true;
        
        if(!$this->getOwner()->isNewRecord){
            if(!$aro->may($this->getOwner(), 'update'))
                throw new RuntimeException('You are not allowed to update this record');            
        }
        else{
            if(!$aro->may(get_class($this->getOwner()), 'create'))
                    throw new RuntimeException('You are not allowed to create this object');
        }
        
        return true;
    }
    
    /**
     * This method checks whether the user has the right to delete the current record
     * 
     */
    public function beforeDelete($event){
        $aro = RestrictedActiveRecord::getUser();
        $owner = $this->getOwner();
        
        //If he's generally allowed, don't filter at all
        if(RestrictedActiveRecord::mayGenerally(get_class($this->getOwner()), 'delete'))
            return true;
            
        if(!$aro->may($owner, 'delete'))
                throw new RuntimeException('You are not allowed to delete this record');
        
        //Ok he has the right to do that - remove all the ACL-objects associated with this object
        $class = Strategy::getClass('Aco');
        $aco = $class::model()->find('model = :model AND foreign_key = :key', array(':model' => get_class($owner), ':key' => $owner->id));
        if(!$aco)
            throw new RuntimeException('No associated Aco!');
        
        if(!$aco->delete())
            throw new RuntimeException('Unable to delete associated Aco');
        
        return true;
    }
    
    
    /**
     * This method takes care to assign individual rights to newly created objects
     * 
     * @param CEvent $evt 
     */
    public function afterSave($event){
        $owner = $this->getOwner();
        if($owner->isNewRecord){
            $aro = RestrictedActiveRecord::getUser();
            //As the object is newly created, it needs a representation
            //If strict mode is disabled, this is not necessary
            $class = Strategy::getClass('Aco');
            $aco = new $class();
            $aco->model = get_class($owner);
            $aco->foreign_key = $owner->getPrimaryKey();
            
            if(!$aco->save()){
                throw new RuntimeException('Unable to create corresponding Aco for new '.get_class($owner));
            }
            
            $aro->grant($aco, RestrictedActiveRecord::getAutoPermissions($this->getOwner()), true);
        }
    }
    
    
    /**
     * Checks whether the current ARO has the given permission on this object
     * @param string $permission 
     */
    public function grants($permission){
        $aro = RestrictedActiveRecord::getUser();
        return $aro->may($this->getOwner(), $permission);
    }
    
    
}
?>