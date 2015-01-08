<?php
/**
 * FileMaker API for PHP
 *
 * @package FileMaker
 *
 * Copyright � 2005-2007, FileMaker, Inc. All rights reserved.
 * NOTE: Use of this source code is subject to the terms of the FileMaker
 * Software License which accompanies the code. Your use of this source code
 * signifies your agreement to such license terms and conditions. Except as
 * expressly granted in the Software License, no other copyright, patent, or
 * other intellectual property license or right is granted, either expressly or
 * by implication, by FileMaker.
 */

/**#@+
 * @ignore Include delegate.
 */
require_once dirname(__FILE__) . '/Implementation/ResultImpl.php';
/**#@-*/

/**
 * Result set description class. Contains all the information about a set of 
 * records returned by a command. 
 *
 * @package FileMaker
 */
class FileMaker_Result
{
    /**
     * The delegate that implements this response.
     *
     * @var FileMaker_Result_Implementation
     * @access private
     */
    var $_impl;

    /**
     * Result object constructor.
     *
     * @param FileMaker_Implementation &$fm FileMaker_Implementation object 
     *        that this result came from.
     */
    function FileMaker_Result(&$fm)
    {
        $this->_impl = new FileMaker_Result_Implementation($fm);
    }

    /**
     * Returns a FileMaker_Layout object that describes the layout of this 
     * result set.
     *
     * @return FileMaker_Layout Layout object.
     */
    function &getLayout()
    {
        return $this->_impl->getLayout();
    }

    /**
     * Returns an array containing each record in the result set. 
     * 
     * Each member of the array is a FileMaker_Record object, or an
     * instance of the alternate class you specified to use for records
     * (see {@link FileMaker_Record}. The array may be empty if 
     * the result set contains no records.
     *
     * @return array Record objects.
     */
    function &getRecords()
    {
        return $this->_impl->getRecords();
    }

    /**
     * Returns a list of the names of all fields in the records in 
     * this result set. 
     * 
     * Only the field names are returned. If you need additional 
     * information, examine the Layout object provided by the 
     * {@link getLayout()} method.
     *
     * @return array List of field names as strings.
     */
    function getFields()
    {
        return $this->_impl->getFields();
    }

    /**
     * Returns the names of related tables for all portals present in records 
     * in this result set.
     *
     * @return array List of related table names as strings.
     */
    function getRelatedSets()
    {
        return $this->_impl->getRelatedSets();
    }

    /**
     * Returns the number of records in the table that was accessed.
     *
     * @return integer Total record count in table.
     */
    function getTableRecordCount()
    {
        return $this->_impl->getTableRecordCount();
    }

    /**
     * Returns the number of records in the entire found set.
     *
     * @return integer Found record count.
     */
    function getFoundSetCount()
    {
        return $this->_impl->getFoundSetCount();
    }

    /**
     * Returns the number of records in the filtered result set.
     * 
     * If no range parameters were specified on the Find command, 
     * then this value is equal to the result of the {@link getFoundSetCount()}
     * method. It is always equal to the value of 
     * count($response->{@link getRecords()}).
     *
     * @return integer Filtered record count.
     */
    function getFetchCount()
    {
        return $this->_impl->getFetchCount();
    }
    
    /**
     * Returns the first record in this result set.
     *
     * @return FileMaker_Record First record.
     */
    function getFirstRecord()
    {
    	return $this->_impl->getFirstRecord();
    }
    
    /**
     * Returns the last record in this result set.
     *
     * @return FileMaker_Record Last record.
     */
   	function getLastRecord()
    {
    	return $this->_impl->getLastRecord();
    }

}
