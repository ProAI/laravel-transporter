<?php

namespace ProAI\Transporter\Contracts;

use GraphQL\Language\AST\Node;

interface ParsesLiteral
{
    /**
     * @param  \GraphQL\Language\AST\Node  $valueNode
     * @param  mixed[]|null  $variables
     * @return mixed
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null): mixed;
}
