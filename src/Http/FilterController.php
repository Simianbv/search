<?php
/**
 * The Filter controller can be used to determine what applicable filters are allowed to set on a given model's resource.
 */

namespace Simianbv\Search\Http;

use Simianbv\Search\FilterGenerator;
use Illuminate\Routing\Controller;
use Exception;

/**
 * Class FilterController
 *
 * @package App\Http\Controllers\Api
 */
class FilterController extends Controller
{

    protected $exclude_from_acl_validation = true;

    /**
     * Returns the Filter builder generator and returns an array containing all the filters applicable to the model
     * provided. If the model has relations defined, it will return the relations as well.
     *
     * @param string $model
     *
     * @return array
     */
    public function getFiltersByModel (string $model = '')
    {
        try {
            if (!$model) {
                if (!$model = request()->get('model')) {
                    throw new Exception("No Model name provided, not in the uri nor in the query parameters. Therefore we're unable to process filters.");
                }
            }

            $model = '\\App\\Models\\' . ucfirst($model);
            $generator = new FilterGenerator($model);
            return ['filters' => $generator->getFilters()];
        } catch (Exception $e) {
            return response(['message' => 'Unable to process model for filter generation', 'exception' => $e->getMessage()], 409);
        }
    }
}
