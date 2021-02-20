<?php

namespace Indigo\ConnectionExtend\Config;

use Magento\Framework\Config\Dom\UrnResolver;
use Magento\Framework\Config\Dom\ValidationSchemaException;
use Magento\Framework\Phrase;

class Dom extends \Magento\Framework\Config\Dom
{
    protected $validateState;

    /**
     * @var \Magento\Framework\Module\Dir\Reader
     */
    protected $moduleReader;

    /**
     * @var \Magento\Framework\Config\Dom\UrnResolver
     */
    private static $urnResolver;

    /**
     * @var array
     * @since 2.2.0
     */
    private static $resolvedSchemaPaths = [];

    private static $connectionList = null;

    const configPath = 'system/connection_extend/list';

    public function __construct(
        $xml,
        \Magento\Framework\Config\ValidationStateInterface $validationState,
        array $idAttributes = [],
        $typeAttributeName = null,
        $schemaFile = null,
        $errorFormat = \Magento\Framework\Config\Dom::ERROR_FORMAT_DEFAULT
    ) {
        $this->validateState = $validationState;
        parent::__construct($xml, $validationState, $idAttributes, $typeAttributeName, $schemaFile, $errorFormat);
    }

    /**
     * Retrieve array of xml errors
     *
     * @param string $errorFormat
     * @return string[]
     * @since 2.1.0
     */
    private static function getXmlErrors($errorFormat)
    {
        $errors = [];
        $validationErrors = libxml_get_errors();
        if (count($validationErrors)) {
            foreach ($validationErrors as $error) {
                $errors[] = self::_renderErrorMessage($error, $errorFormat);
            }
        } else {
            $errors[] = 'Unknown validation error';
        }
        return $errors;
    }

    /**
     * Render error message string by replacing placeholders '%field%' with properties of \LibXMLError
     *
     * @param \LibXMLError $errorInfo
     * @param string $format
     * @return string
     * @throws \InvalidArgumentException
     */
    private static function _renderErrorMessage(\LibXMLError $errorInfo, $format)
    {
        $result = $format;
        foreach ($errorInfo as $field => $value) {
            $placeholder = '%' . $field . '%';
            $value = trim((string)$value);
            $result = str_replace($placeholder, $value, $result);
        }
        if (strpos($result, '%') !== false) {
            if (preg_match_all('/%.+%/', $result, $matches)) {
                $unsupported = [];
                foreach ($matches[0] as $placeholder) {
                    if (strpos($result, $placeholder) !== false) {
                        $unsupported[] = $placeholder;
                    }
                }
                if (!empty($unsupported)) {
                    throw new \InvalidArgumentException(
                        "Error format '{$format}' contains unsupported placeholders: " . implode(', ', $unsupported)
                    );
                }
            }
        }
        return $result;
    }

    public static function validateDomDocument(
        \DOMDocument $dom,
        $schema,
        $errorFormat = self::ERROR_FORMAT_DEFAULT
    ) {
        if (!function_exists('libxml_set_external_entity_loader')) {
            return [];
        }

        if (!self::$urnResolver) {
            self::$urnResolver = new UrnResolver();
        }
        if (!isset(self::$resolvedSchemaPaths[$schema])) {
            self::$resolvedSchemaPaths[$schema] = self::$urnResolver->getRealPath($schema);
        }
        $schema = self::$resolvedSchemaPaths[$schema];

        libxml_use_internal_errors(true);
        libxml_set_external_entity_loader([self::$urnResolver, 'registerEntityLoader']);
        $errors = [];
        try {
            $schemaContent = file_get_contents($schema);
            $nodeString = '<xs:simpleType name="resourceType">';
            $restrictionString = '<xs:restriction base="xs:string">';
            if (strpos($schemaContent, $nodeString) !== false) {
                // ObjectManager instead of di for Filesystem::_createConfigMerger to run with correct number of argument
                // Create ResourceConnection instead of ConfigInterface to avoid dependency circular
                if(!self::$connectionList){
                    self::$connectionList = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\App\ResourceConnection::class)
                        ->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION)->fetchRow('SELECT value from core_config_data where path = "' . self::configPath . '"')['value'];
                }
                $connectionString = [];
                foreach (explode(',', self::$connectionList) as $connection) {
                    $connection = trim($connection);
                    $connectionString[] = "<xs:enumeration value=\"$connection\" />";
                }
                $content1 = explode($nodeString, $schemaContent);
                $content2 = explode($restrictionString, $content1[1], 2);
                $newSchemaContent = $content1[0] . $nodeString . $content2[0] . $restrictionString . PHP_EOL .
                    implode(PHP_EOL, $connectionString) . $content2[1];
                $result = $dom->schemaValidateSource($newSchemaContent);
            } else {
                $result = $dom->schemaValidate($schema);
            }
            if (!$result) {
                $errors = self::getXmlErrors($errorFormat);
            }
        } catch (\Exception $exception) {
            $errors = self::getXmlErrors($errorFormat);
            libxml_use_internal_errors(false);
            array_unshift($errors, new Phrase('Processed schema file: %1', [$schema]));
            throw new ValidationSchemaException(new Phrase(implode("\n", $errors)));
        }
        libxml_set_external_entity_loader(null);
        libxml_use_internal_errors(false);
        return $errors;
    }
}
