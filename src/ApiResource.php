<?php

/**
 * @copyright (c) Simian B.V. 2019
 * @version       1.0.0
 */

namespace Simianbv\Search;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Simianbv\Search\Contracts\RelationGuardInterface;

/**
 * @class   ApiResource
 * @package App\Http\Resources
 */
class ApiResource extends JsonResource
{
    /**
     * @var Collection
     */
    protected $messages;

    /**
     * @var null
     */
    protected $builder = null;

    /**
     * @var array
     */
    private $_append_data = null;

    /**
     * @var Builder|EloquentCollection|Model|null|Object|string|static|static[]
     */
    private $_base_object = null;

    /**
     * ApiResource constructor.
     *
     * @param string|Object|Builder|Model $identifier
     * @param string|Object|Builder|Model $target
     * @param array|null                  $messages
     *
     * @throws Exception
     * @todo: test if backwards compatible in full
     *
     */
    public function __construct($identifier, $target = null, $messages = null)
    {
        if ($identifier instanceof EloquentCollection) {
            throw new Exception("The identifier provided is a collection, either use ->first() or return a ApiCollection Response");
        }

        $object = null;
        $originalModel = null;

        if (is_array($target)) {
            $messages = $target;
            $target = null;
        }

        if ($messages !== null) {
            $this->addMessages($messages);
        }

        if ($identifier instanceof Model) {
            $originalModel = $identifier;
            $target = $identifier->getModel()->newQuery();
            $identifier = $identifier->getKey();
        }

        if (!$target) {
            throw new Exception("Unable to determine what the target Model and corresponding Builder should be.");
        }


        $this->setBuilder($target);
        $object = $this->getWithRelatedModel($identifier);

        if (!$object) {
            if ($identifier instanceof Builder) {
                $object = $identifier->first();
            } else {
                if (
                    is_string($identifier) && (is_string($target) || $target instanceof Builder || $target instanceof Model)) {
                    $object = $target::find($identifier);
                } else {
                    if ($originalModel) {
                        $object = $originalModel;
                    } else {
                        throw new Exception('Unable to process the target model, have you made sure a target model has been provided?');
                    }
                }
            }
        }
        $this->_base_object = $object;

        parent::__construct($object);
    }

    /**
     * Returns the Model it as given as the argument in the constructor.
     *
     * @return EloquentCollection|Model|null|Object|string|static|static[]
     */
    public function getResourceModel()
    {
        return $this->_base_object;
    }

    /**
     * Validate whether or not there are relations to be loaded using the "with" parameter flag.
     * If so, load in the target builder, apply the with relations and return the model associated with the
     * identifier.
     * Returns null by default.
     *
     * @param mixed $identifier
     *
     * @return Model|Builder|null
     */
    public function getWithRelatedModel($identifier)
    {
        if ($withRequest = request()->input('with')) {
            if ($this->builder->getModel() instanceof RelationGuardInterface) {
                $parts = array_map('trim', explode(',', $withRequest));
                foreach ($parts as $with) {
                    $guardedRelations = $this->builder->getModel()::getGuardedRelations();

                    // @todo: add a way to verify the ACL "Acl::validate($guardedRelations[$with])"

                    if (array_key_exists($with, $guardedRelations)) {
                        $this->builder->with($with);
                    }
                }
            }
            return $this->builder->where($this->builder->getModel()->getKeyName(), $identifier)->first();
        }
        return null;
    }

    /**
     * Set up the builder either by determining the class, the model or the builder instance.
     *
     * @param mixed $target
     *
     * @return void
     */
    protected function setBuilder($target)
    {
        if ($target instanceof Builder) {
            $this->builder = $target;
        }

        if ($target instanceof Model) {
            $this->builder = $target->newQuery();
        }

        if (is_string($target)) {
            if (!class_exists($target)) {
                throw new SearchException("Unable to determine that the class exists.");
            }

            $model = new $target;
            if (!$model instanceof Model) {
                throw new SearchException("Unable to perform search filters on a non-model object.");
            }

            $this->builder = $model->newQuery();
        }
    }

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function toArray($request)
    {
        $data = [
            'resource' => $this->resource->toArray(),
            'meta' => [],
        ];

        if ($this->_append_data !== null) {
            if (is_array($this->_append_data)) {
                foreach ($this->_append_data as $key => $value) {
                    $data[$key] = $value;
                }
            }
        }

        if ($this->messages instanceof Collection) {
            $data['messages'] = $this->messages->toArray();
        }

        return $data;
    }

    /**
     * Append data to the output
     *
     * @param $data
     *
     * @return ApiResource
     */
    public function append($data)
    {
        $this->_append_data = $data;


        return $this;
    }

    /**
     * @param $messages
     */
    public function addMessages($messages)
    {
        if (is_array($messages)) {
            $messages = new Collection($messages);
        } else {
            if (!$messages instanceof Collection) {
                // raise an error somehow?
                // @todo: solve messages array/collection issue?
            }
        }

        if ($messages instanceof Collection) {
            $this->messages = $messages;
        }
    }
}
