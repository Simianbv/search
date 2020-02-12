<?php

/**
 * @copyright (c) Simian B.V. 2019
 * @version       1.0.0
 */

namespace Simianbv\Search\Contracts;

/**
 * @interface RelationGuardInterface
 * @package   Simianbv\Search\Contracts
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
