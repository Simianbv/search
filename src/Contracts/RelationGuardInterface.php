<?php
/**
 * Copyright (c) 2019.
 */

namespace App\Lightning\Contracts;

/**
 * @interface RelationGuard
 * @package   App\Lightning\Contracts
 */
interface RelationGuard
{
    /**
     * Return an array containing a key/value for the ACL which can be used to validate
     * if the current user has permissions to actually view this relation.
     *
     * @example
     *         return [
     *              'relation' => 'acl.token.code'
     *         ]
     *
     * @return array
     */
    public static function getGuardedRelations(): array;
}
