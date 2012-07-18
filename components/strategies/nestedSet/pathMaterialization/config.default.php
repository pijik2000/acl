<?php

return array(
    
    /**
     * The Prefix of your classes
     * @see Strategy::getClass 
     */
    'prefix' => 'Pm',
    
    /**
     * Enables or disables the strict Mode
     * If the strict mode is disabled, objects which are not found are simly created
     * on the fly (Access Control Objects as well as Access Request Objects) 
     */
    'strictMode' => false,
    
    /**
     * The permissions of the Guest-Group will used whenever the requesting object
     * cannot be determined (for example if the user isn't logged in)
     * If you want to disable the usage of Guest-groups completely, just set ot to 
     * NULL 
     */
    'guestGroup' => 'Guest',
    /**
     * Enables the access-check in two layers: if enabled, the access will be firstly checked
     * against a general permission-system and only (and only if) if that returns false, the 
     * regular lookup will take place  
     */
    'enableGeneralPermissions' => false,
    /**
     * Enables the business rules for all actions (automatical lookup) _except_ the read-action
     */
    'enableBusinessRules' => false,
    /**
     * Sets the direction in which business-rules are applied. Default is to check
     * both sides 
     * possible values: "both", "aro" and "aco"
     */
    'lookupBusinessRules' => 'both',
    /**
     * Defines which permissions are automatically assigned to the creator of an object upon its creation
     * default: all
     * You can overwrite this using the autoPermissions-value of each object 
     */
    'autoPermissions' => '*',
    /**
     * Enables the restriction of the grant-action
     * Note: If you enable this and assign no autoPermissions at all, only general permissions will
     * grant something at all 
     */
    'enableGrantRestriction' => false,
    /**
     * Enables you to restrict WHAT an Aro can grant, if it can grant something at all
     */
    'enableSpecificGrantRestriction' => false,
    /**
     * Permissions which are allowed for all the users on all objects
     * default: only create 
     */
    'generalPermissions' => array('create'),
);

?>