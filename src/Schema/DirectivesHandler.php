<?php

namespace ProAI\Transporter\Schema;

use Exception;
use GraphQL\Executor\Values;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive as BaseDirective;
use GraphQL\Type\Definition\EnumType as BaseEnumType;
use GraphQL\Type\Definition\InputObjectType as BaseInputObjectType;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Arr;
use ProAI\Transporter\Type\Definition\Directive;
use ProAI\Transporter\Type\Directives;

class DirectivesHandler
{
    /**
     * The directive instances cache.
     *
     * @var \GraphQL\Type\Definition\Directive[]
     */
    protected array $directives;

    /**
     * Create a new ast definition builder instance.
     *
     * @param  \GraphQL\Type\Definition\Directive[]  $directives
     * @return void
     */
    public function __construct(array $directives)
    {
        $directives = $this->useGraphQLDirectives($directives);

        $directives = $this->useTransporterDirectives($directives);

        $this->directives = $directives;
    }

    /**
     * Use built-in graphql directives.
     *
     * @param  \GraphQL\Type\Definition\Directive[]  $directives
     * @return \GraphQL\Type\Definition\Directive[]
     */
    protected function useGraphQLDirectives(array $directives): array
    {
        $directiveNames = array_map(function (mixed $directive) {
            return $directive->name;
        }, $directives);

        if (! in_array('skip', $directiveNames)) {
            $directives[] = BaseDirective::skipDirective();
        }
        if (! in_array('include', $directiveNames)) {
            $directives[] = BaseDirective::includeDirective();
        }
        if (! in_array('deprecated', $directiveNames)) {
            $directives[] = BaseDirective::deprecatedDirective();
        }

        return $directives;
    }

    /**
     * Use built-in transporter directives.
     *
     * @param  \GraphQL\Type\Definition\Directive[]  $directives
     * @return \GraphQL\Type\Definition\Directive[]
     */
    protected function useTransporterDirectives(array $directives): array
    {
        $directiveNames = array_map(function (mixed $directive) {
            return $directive->name;
        }, $directives);

        $transporterDirectives = Directives::getTransporterDirectives();

        foreach ($transporterDirectives as $transporterDirective) {
            if (in_array($transporterDirective->name, $directiveNames)) {
                continue;
            }

            $directives[] = $transporterDirective;
        }

        return $directives;
    }

    /**
     * Get directives.
     *
     * @return \GraphQL\Type\Definition\Directive[]
     */
    public function getDirectives(): array
    {
        return $this->directives;
    }

    /**
     * Apply directives.
     *
     * @param  \GraphQL\Language\AST\TypeDefinitionNode  $def
     * @param  \GraphQL\Type\Definition\Type  $type
     * @return void
     */
    public function applyDirectives(TypeDefinitionNode $def, Type $type): void
    {
        if ($def instanceof ObjectTypeDefinitionNode) {
            $this->visit(DirectiveLocation::OBJECT, $def, $type);
            $this->visitFields($def, $type);
        } elseif ($def instanceof InterfaceTypeDefinitionNode) {
            $this->visit(DirectiveLocation::IFACE, $def, $type);
            $this->visitFields($def, $type);
        } elseif ($def instanceof EnumTypeDefinitionNode && $type instanceof BaseEnumType) {
            $this->visit(DirectiveLocation::ENUM, $def, $type);
            $this->visitEnumValues($def, $type);
        } elseif ($def instanceof UnionTypeDefinitionNode) {
            $this->visit(DirectiveLocation::UNION, $def, $type);
        } elseif ($def instanceof ScalarTypeDefinitionNode) {
            $this->visit(DirectiveLocation::SCALAR, $def, $type);
        } elseif ($def instanceof InputObjectTypeDefinitionNode && $type instanceof BaseInputObjectType) {
            $this->visit(DirectiveLocation::INPUT_OBJECT, $def, $type);
            $this->visitInputFields($def, $type);
        } else {
            throw new Exception('Type definition "'.get_class($def).'" not supported.');
        }
    }

    /**
     * Visit directive location.
     *
     * @param  string  $location
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode|\GraphQL\Language\AST\InterfaceTypeDefinitionNode|\GraphQL\Language\AST\EnumTypeDefinitionNode|\GraphQL\Language\AST\UnionTypeDefinitionNode|\GraphQL\Language\AST\ScalarTypeDefinitionNode|\GraphQL\Language\AST\InputObjectTypeDefinitionNode|\GraphQL\Language\AST\FieldDefinitionNode|\GraphQL\Language\AST\InputValueDefinitionNode|\GraphQL\Language\AST\EnumValueDefinitionNode  $def
     * @param  object  $item
     * @return void
     */
    protected function visit(string $location, Node $def, object $item): void
    {
        foreach ($def->directives as $node) {
            $name = $node->name->value;
            $directive = Arr::first(
                $this->directives,
                function (mixed $directive) use ($name) {
                    return $directive->name === $name;
                }
            );

            // Do not apply visitors for other directives than the transporter
            // directive.
            if (! $directive instanceof Directive) {
                continue;
            }

            $args = Values::getDirectiveValues($directive, $def);

            $directive->visit($location, $item, $args);
        }
    }

    /**
     * Visit FIELD_DEFINITION/ARGUMENT_DEFINITION locations.
     *
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode|\GraphQL\Language\AST\InterfaceTypeDefinitionNode  $def
     * @param  object  $item
     * @return void
     */
    protected function visitFields(ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode $def, object $item): void
    {
        $fields = $item->getFields();

        foreach ($def->fields as $fieldDef) {
            $field = $fields[$fieldDef->name->value];

            $this->visit(
                DirectiveLocation::FIELD_DEFINITION,
                $fieldDef,
                $field
            );

            foreach ($fieldDef->arguments as $argumentDef) {
                $name = $argumentDef->name->value;

                $this->visit(
                    DirectiveLocation::ARGUMENT_DEFINITION,
                    $argumentDef,
                    $field->getArg($name)
                );
            }
        }
    }

    /**
     * Visit ENUM_VALUE locations.
     *
     * @param  \GraphQL\Language\AST\EnumTypeDefinitionNode  $def
     * @param  \GraphQL\Type\Definition\EnumType  $type
     * @return void
     */
    protected function visitEnumValues(EnumTypeDefinitionNode $def, BaseEnumType $type): void
    {
        foreach ($def->values as $valueDef) {
            $value = $type->getValue($valueDef->name->value);

            $this->visit(DirectiveLocation::ENUM_VALUE, $valueDef, $value);
        }
    }

    /**
     * Visit INPUT_FIELD_DEFINITION locations.
     *
     * @param  \GraphQL\Language\AST\InputObjectTypeDefinitionNode  $def
     * @param  \GraphQL\Type\Definition\InputObjectType  $type
     * @return void
     */
    protected function visitInputFields(InputObjectTypeDefinitionNode $def, BaseInputObjectType $type): void
    {
        $fields = $type->getFields();

        foreach ($def->fields as $fieldDef) {
            $this->visit(
                DirectiveLocation::INPUT_FIELD_DEFINITION,
                $fieldDef,
                $fields[$fieldDef->name->value]
            );
        }
    }
}
