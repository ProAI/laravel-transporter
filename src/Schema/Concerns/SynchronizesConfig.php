<?php

namespace ProAI\Transporter\Schema\Concerns;

use GraphQL\Type\Definition\Type;
use ProAI\Transporter\Type\Definition\EnumType;
use ProAI\Transporter\Type\Definition\InputObjectType;
use ProAI\Transporter\Type\Definition\InterfaceType;
use ProAI\Transporter\Type\Definition\ObjectType;

trait SynchronizesConfig
{
    /**
     * Sync config of type.
     *
     * @param  \GraphQL\Type\Definition\Type  $type
     * @return void
     */
    protected function syncConfig(Type $type)
    {
        switch (true) {
            case $type instanceof ObjectType:
            case $type instanceof InterfaceType:
                $this->syncFieldsConfig($type);
                break;
            case $type instanceof EnumType:
                $this->syncEnumValuesConfig($type);
                break;
            case $type instanceof InputObjectType:
                $this->syncInputFieldsConfig($type);
                break;
        }
    }

    /**
     * Sync type fields config with config of fields and field arguments.
     *
     * @param  \ProAI\Transporter\Type\Definition\ObjectType|\ProAI\Transporter\Type\Definition\InterfaceType  $type
     * @return void
     */
    protected function syncFieldsConfig(ObjectType|InterfaceType $type)
    {
        $syncedFields = [];

        foreach ($type->getFields() as $field) {
            foreach ($field->args as $arg) {
                $field->config['args'][$arg->name] = $arg->config;
            }

            $syncedFields[$field->name] = $field->config;
        }

        $type->config['fields'] = $syncedFields;
    }

    /**
     * Sync enum type values config with config of enum values.
     *
     * @param  \ProAI\Transporter\Type\Definition\EnumType  $type
     * @return void
     */
    protected function syncEnumValuesConfig(EnumType $type)
    {
        $syncedValues = [];

        foreach ($type->getValues() as $value) {
            $syncedValues[$value->name] = $value->config;
        }

        $type->config['values'] = $syncedValues;
    }

    /**
     * Sync type fields config with config of fields.
     *
     * @param  \ProAI\Transporter\Type\Definition\InputObjectType  $type
     * @return void
     */
    protected function syncInputFieldsConfig(InputObjectType $type)
    {
        $syncedFields = [];

        foreach ($type->getFields() as $field) {
            $syncedFields[$field->name] = $field->config;
        }

        $type->config['fields'] = $syncedFields;
    }
}
