<?php

namespace ProAI\Transporter\Resolvers;

abstract class Resolver
{
    use AuthorizesFields, DispatchesJobs, ValidatesFields;

    /**
     * Create a new resolver instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }
}
