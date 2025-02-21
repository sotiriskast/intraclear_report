<?php


namespace App\Api\V1\Filters;

use App\Services\DynamicLogger;
use Illuminate\Database\Eloquent\Builder;

abstract class QueryFilter
{
    protected Builder $query;

    public function __construct(Builder $query, protected DynamicLogger $logger)
    {
        $this->query = $query;
    }

    abstract public function apply(array $filters): Builder;

}
