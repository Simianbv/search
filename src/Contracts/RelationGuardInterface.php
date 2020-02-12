<?php
/**
 * Copyright (c) 2019.
 */

namespace App\Lightning\Contracts;

/**
 * @interface RelationGuardInterface
 * @package   App\Lightning\Contracts
 */
interface RelationGuardInterface
{
    /**
     * Return an array containing a key/value for the ACL which can be used to validate
     * if the current user has permissions to actually view this relation.
     *
     * @return array
     * @example
     *      return [
     *          'relation' => 'acl.token.code'
     *      ]
     *
     */
    public static function getGuardedRelations(): array;
}
