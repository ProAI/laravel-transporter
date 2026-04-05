<?php

namespace ProAI\Transporter\Contracts;

use ProAI\Transporter\ReverseRelation;

interface HasParent
{
    /**
     * Get the relation to the parent model.
     *
     * @return \ProAI\Transporter\ReverseRelation
     */
    public function parent(): ReverseRelation;
}
