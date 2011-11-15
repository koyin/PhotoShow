<?php
/**
 * This file implements the class Account.
 * 
 * PHP versions 4 and 5
 *
 * LICENSE:
 * 
 * This file is part of PhotoShow.
 *
 * PhotoShow is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PhotoShow is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PhotoShow.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package   PhotoShow
 * @category  Website
 * @author    Thibaud Rohmer <thibaud.rohmer@gmail.com>
 * @copyright 2011 Thibaud Rohmer
 * @license   http://www.gnu.org/licenses/
 * @link      http://github.com/thibaud-rohmer/PhotoShow-v2
 */

/**
 * Account
 *
 * Implements functions to work with a User account.
 * Read the account from the Accounts XML file,
 * edit it, and save it.
 * 
 * Account Structure:
 * - Login
 * - Name
 * - Password (Encryption : sha1)
 * - Email
 * - Groups -> Group names
 *
 * 
 * @package   PhotoShow
 * @category  Website
 * @author    Thibaud Rohmer <thibaud.rohmer@gmail.com>
 * @copyright Thibaud Rohmer
 * @license   http://www.gnu.org/licenses/
 * @link      http://github.com/thibaud-rohmer/PhotoShow-v2
 */
class Account
{
	/// Login of the user
    private $login;

	/// Password of the user, encrypted via sha1
    public $password;

	/// Name of the user (optional)
    public $name;

	/// Email of the user (optional)
    public $email;

	/// List of the groups the user is in. No duplicates. Minimum list : array("user")
    public $groups;
    
    /**
     * Find account in the base.
     *
     * @param string $login 
     * @author Thibaud Rohmer
     */
    public function __construct($login=NULL){
        if(!isset($login))
            return;
        
        $xml_infos  =   Settings::$accounts_file
        $xml        =   simplexml_load_file($xml_infos);

		// Look each account
        foreach( $xml as $account ){
	
            if((string)$account->login == $login){
                $this->login    = (string)$account->login;
                $this->password = (string)$account->pass;
                $this->name     = (string)$account->name;
                $this->email    = (string)$account->email;
                foreach($account->groups->children() as $group)
                    $this->groups[] = (string)$group;
                return;
            }
        }
        throw new Exception("$login not found");
    }
    
    /**
     * Creates a new account in the base
     *
     * @param string $login 
     * @param string $password 
     * @author Thibaud Rohmer
     */ 
    public static function create($login,$password,$groups=array(),$name='',$email=''){
        // Check if login already exists
        if(self::exists($login))
            throw new Exception("$login already exists");

        // All users belong to the "user" group
        $groups[]="user";
        
        try{
            // Look for accounts file
            $xml_infos=Settings::$accounts_file;
        }catch(FileException $e){
            // No accounts file found
            
            // Create accounts file
            $xml        =   new SimpleXMLElement('<account></account>');
            $xml->asXML($e->file);
            
            // Set this account as root
            $groups[] = "root";
        }

        $acc            =   new Account();
        $acc->login     =   $login;
        $acc->password  =   self::password($password);
        $acc->groups    =   $groups;
        $acc->name      =   $name;
        $acc->email     =   $email;
        
        $acc->save();
    }
    
    /**
     * Edit account password
     *
     * @param string $password 
     * @return void
     * @author Thibaud Rohmer
     */
    public static function password($password){
        return sha1($password);
    }
    
    /**
     * Add a group to this user
     *
     * @param string $group 
     * @return void
     * @author Thibaud Rohmer
     */
    public function add_group($group){
        // Check that this user doesn't already belong to this group
        if(!in_array($group,$this->groups)){
            $this->groups[]=$group;
            $g = new Group($group);
            $g->save();
        }
    }
    
    /**
     * Remove a group from this user
     *
     * @param string $group 
     * @return void
     * @author Thibaud Rohmer
     */
    public function remove_group($group){
        // Check that this user belongs to this group
        if(in_array($group,$this->groups)){
            $id=array_search($group,$this->groups);
            unset ( $this->groups[$id] );
        }
    }
    
    /**
     * Save account in the base
     *
     * @return void
     * @author Thibaud Rohmer
     */
    public function save(){
        $xml_infos  =   Settings::$accounts_file
        $xml        =   simplexml_load_file($xml_infos);
        
        foreach( $xml as $acc ){
            if((string)$acc->login == $this->login){
                $account=$acc;
                break;
            }
        }
        if(isset($account)){        
            $account->password  =   $this->password;
            $account->name      =   $this->name;
            $account->email     =   $this->email;
            unset($account->groups);
        }else{
            $account=$xml->addChild('account');
            $account->addChild(     'login' ,       $this->login);
            $account->addChild(     'password', $this->password);
            $account->addChild(     'name'  ,       $this->name);
            $account->addChild(     'email' ,       $this->email);
        }
        // Create the groups
        $account->addChild('groups');
        foreach($this->groups as $group){
            $account->addChild('group',$group);
            
            try{
                $g  =   new group($g);
                $g->save();
            }catch(Exception $e){
                // This group already exists
            }
        }
        
        // Saving into file
        $xml->asXML($xml_infos);
    }
    
    /**
     * Delete an account
     *
     * @param string $login 
     * @return void
     * @author Thibaud Rohmer
     */
    public static function delete($login){
        Settings::$accounts_file;
        $xml        =   simplexml_load_file($xml_infos);
        
        foreach( $xml as $acc ){
            if((string)$acc->login == $login){
                unset($acc);
            }
        }
        
        $xml->asXML($xml_infos);
    }

    /**
     * Check if an account already exists
     *
     * @param string $login
     * @return bool
     * @author Thibaud Rohmer
     */
    public static function exists($login){
		// Settings returns an exception if no admin file was found.
        try{
        	new Settings();
        }catch(Exception $e){
            // No file, no user !
            return false;
        }
        $xml_infos  =   Settings::$accounts_file
        $xml        =   simplexml_load_file($xml_infos);

        foreach( $xml as $account ){
            if((string)$account->login == $login)
                return true;
        }
    
        return false;
    }
    
    
    /**
     * Returns an array containing all accounts
     *
     * @return array $accounts
     * @author Thibaud Rohmer
     */
    public static function findAll(){
        $accounts   =   array();
        
        $xml_infos  =   Settings::$accounts_file
        $xml        =   simplexml_load_file($xml_infos);

        foreach( $xml as $account ){
            $new_acc=array();
            
            $new_acc['password']    = $account->password;
            $new_acc['name']        = $account->name;
            $new_acc['email']       = $account->email;
            foreach($accounts->groups->children() as $group)
                $new_acc['groups'][]= $group;
                
            $accounts[]=$new_acc;
        }
        
        return $accounts;
    }
    
    /**
     * Returns the rights of an account
     *
     * @param string $login 
     * @return void
     * @author Thibaud Rohmer
     */
    public static function rights($login){
        $rights =   array();

        $xml_infos  =   Settings::$accounts_file
        $xml        =   simplexml_load_file($xml_infos);

        foreach( $xml as $account ){
        if($account->login==$login){
            foreach($account->groups->children() as $group){
                $rights=array_unique(array_merge($rights,Groups::rights($group)));
            }
        }
        }

        return $rights;
    }
}


?>