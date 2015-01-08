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
 * @ignore Include parent class.
 */
require_once dirname(__FILE__) . '/../Error.php';
/**#@-*/

/**
 * Extension of the FileMaker_Error class that adds information about 
 * pre-validation errors.
 *
 * @package FileMaker
 */
class FileMaker_Error_Validation extends FileMaker_Error
{
    /**
     * Error array.
     *
     * @var array
     * @access private
     */
    var $_errors = array();

    /**
     * Adds an error.
     *
     * @param FileMaker_Field $field Field object that failed pre-validation.
     * @param integer $rule Pre-validation rule that failed specified as one 
     *        of the FILEMAKER_RULE_* constants.
     * @param string $value Value that failed pre-validation.
     */
    function addError($field, $rule, $value)
    {
        $this->_errors[] = array($field, $rule, $value);
    }

    /**
     * Indicates whether the error is a detailed pre-validation error 
     * or a FileMaker Web Publishing Engine error.
     *
     * @return boolean TRUE, to indicate that this is a pre-validation 
     *         error object.
     */
    function isValidationError()
    {
        return true;
    }

    /**
     * Returns the number of pre-validation rules that failed.
     *
     * @return integer Number of failures.
     */
    function numErrors()
    {
        return count($this->_errors);
    }

    /**
     * Returns an array of arrays describing the pre-validation errors that 
     * occurred. 
     * 
     * Each entry in the outer array represents a pre-validation failure. 
     * Each failure is represented by a three-element array with the
     * following members:
     *
     * - 0 => The field object for the field that failed pre-validation.
     * - 1 => The pre-validation rule that failed specified as a 
     *        FILEMAKER_RULE_* constant.
     * - 2 => The value entered for the field that failed pre-validation.
     *
     * Multiple pre-validation rules can fail on a single field. If you set the 
     * optional $fieldName parameter, then failures for only the specified 
     * field are returned.
     *
     * @param string $fieldName Name of the field to get errors for. 
     *
     * @return array Pre-validation error details.
     */
    function getErrors($fieldName = null)
    {
        if ($fieldName === null) {
            return $this->_errors;
        }

        $errors = array();
        foreach ($this->_errors as $error) {
            if ($error[0]->getName() == $fieldName) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

}
