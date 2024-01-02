<?php
/**
 * CodingStandard_Sniffs_NamingConventions_ValidVariableNameSniff.
 *
 * PHP version 5
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @author   Greg Sherwood <gsherwood@squiz.net>
 * @author   Marc McIntyre <mmcintyre@squiz.net>
 * @author   Alexander Obuhovich <aik.bold@gmail.com>
 * @license  https://github.com/aik099/CodingStandard/blob/master/LICENSE BSD 3-Clause
 * @link     https://github.com/aik099/CodingStandard
 */

namespace CodingStandard\Sniffs\NamingConventions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\AbstractVariableSniff;
use PHP_CodeSniffer\Util\Common;

/**
 * CodingStandard_Sniffs_NamingConventions_ValidVariableNameSniff.
 *
 * Checks the naming of variables and member variables.
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @author   Greg Sherwood <gsherwood@squiz.net>
 * @author   Marc McIntyre <mmcintyre@squiz.net>
 * @author   Alexander Obuhovich <aik.bold@gmail.com>
 * @license  https://github.com/aik099/CodingStandard/blob/master/LICENSE BSD 3-Clause
 * @link     https://github.com/aik099/CodingStandard
 */
class ValidVariableNameSniff extends AbstractVariableSniff
{

    /** @inheritDoc */
    protected $phpReservedVars = array(
        '_SERVER',
        '_GET',
        '_POST',
        '_REQUEST',
        '_SESSION',
        '_ENV',
        '_COOKIE',
        '_FILES',
        'GLOBALS',
        'http_response_header',
        'HTTP_RAW_POST_DATA',
        'php_errormsg',
    );

    /** @inheritDoc */
    protected function processVariable(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $varName = ltrim($tokens[$stackPtr]['content'], '$');

        // If it's a php reserved var, then it's ok.
        if (in_array($varName, $this->phpReservedVars) === true) {
            return;
        }

        $objOperator = $phpcsFile->findPrevious(array(T_WHITESPACE), ($stackPtr - 1), null, true);
        if ($tokens[$objOperator]['code'] === T_DOUBLE_COLON
            || $tokens[$objOperator]['code'] === T_OBJECT_OPERATOR
        ) {
            // Don't validate class/object property usage,
            // because their declaration is already validated.
            return;
        }

        if (!$this->isCamelCase($varName)) {
            $error = 'Variable "%s" is not in valid camelCase format';
            $data = array($varName);
            $phpcsFile->addError($error, $stackPtr, 'NotCamelCaps', $data);
        }
    }


    /** @inheritDoc */
    protected function processMemberVar(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $varName = ltrim($tokens[$stackPtr]['content'], '$');
        $memberProps = $phpcsFile->getMemberProperties($stackPtr);

        // @codeCoverageIgnoreStart
        if (empty($memberProps) === true) {
            // Couldn't get any info about this variable, which
            // generally means it is invalid or possibly has a parse
            // error. Any errors will be reported by the core, so
            // we can ignore it.
            return;
        }
        // @codeCoverageIgnoreEnd

        $classToken = $phpcsFile->findPrevious(
            array(T_CLASS, T_INTERFACE, T_TRAIT),
            $stackPtr
        );
        $className = $phpcsFile->getDeclarationName($classToken);

        if (!$this->isCamelCase($varName)) {
            $errorData = array($className . '::' . $varName);
            $error = '%s member variable "%s" is not in valid camel caps format';
            $data = array(
                ucfirst($memberProps['scope']),
                $errorData[0],
            );
            $phpcsFile->addError($error, $stackPtr, 'MemberNotCamelCase', $data);
        }
    }


    /** @inheritDoc */
    protected function processVariableInString(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $content = $tokens[$stackPtr]['content'];
        $variablesFound = preg_match_all(
            '|[^\\\]\${?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)|',
            $content,
            $matches,
            PREG_SET_ORDER + PREG_OFFSET_CAPTURE
        );

        if ($variablesFound === 0) {
            return;
        }

        foreach ($matches as $match) {
            $varName = $match[1][0];
            $offset = $match[1][1];

            // If it's a php reserved var, then its ok.
            if (in_array($varName, $this->phpReservedVars) === true) {
                continue;
            }

            // Don't validate class/object property usage in strings,
            // because their declaration is already validated.
            $variablePrefix = substr($content, $offset - 3, 2);
            if ($variablePrefix === '::' || $variablePrefix === '->') {
                continue;
            }

            if (!$this->isCamelCase($varName)) {
                $error = 'Variable in string "%s" is not in valid snake caps format';
                $data = array($varName);
                $phpcsFile->addError($error, $stackPtr, 'StringNotCamelCase', $data);
            }
        }
    }

    protected function isCamelCase(string $string): bool
    {
        return Common::isCamelCaps($string, false, true, false);
    }
}
