<?php

namespace App\Repositories;

use App\Repositories\Contracts\RepositoryInterface;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class GeneralRepository implements RepositoryInterface
{
    /**
     * An instance of the Eloquent model.
     *
     * @var Model
     */
    protected $model;

    /**
     * Instantiate the model.
     */
    public function __construct()
    {
        $modelClass = $this->model();
        $this->model = new $modelClass;
    }

    /**
     * Return the model class name. Must be defined in the concrete repository.
     *
     * @return string
     */
    abstract protected function model(): string;

    /**
     * Retrieve a paginated list of models applying dynamic filters and sorting.
     *
     * @param array $params
     * @return LengthAwarePaginator
     */
    public function all(array $params = []): LengthAwarePaginator
    {
        $query = $this->model::query();

        // Extract pagination limit.
        $limit = $params['limit'] ?? 10;
        unset($params['limit']);

        // Sorting: check for sort_by and sort_dir.
        if (isset($params['sort_by'])) {
            $direction = $params['sort_dir'] ?? 'asc';
            $query->orderBy($params['sort_by'], $direction);
            unset($params['sort_by'], $params['sort_dir']);
        }

        // If using a structured filter input (like an array of filter objects),
        // you can check if a "filters" key exists:
        if (isset($params['filters']) && is_array($params['filters'])) {
            $query = $this->applyFilters($query, $params['filters']);
        } else {
            // Otherwise, assume $params itself is a flat set of filters.
            $query = $this->applyFilters($query, $params);
        }

        return $query->paginate($limit);
    }



    /**
     * Apply a set of dynamic filters to the query.
     *
     * Each filter should be an associative array with keys:
     * - field: the column name
     * - value: the value to compare
     * - fieldType: one of 'text', 'date', 'number', 'boolean', 'set', 'array'
     * - operator: the operator, e.g. 'equals', 'notEqual', 'contains', 'greaterThan', 'inRange', etc.
     * - rangeValue: (optional) used for inRange operator
     *
     * @param  Builder  $query
     * @param array $filters
     * @return Builder
     */
    protected function applyFilters(Builder $query, array $filters)
    : Builder {
        foreach ($filters as $filter) {
            // Ensure required keys exist.
            if (!isset($filter['field'], $filter['operator'])) {
                continue;
            }
            $field = $filter['field'];
            $value = $filter['value'] ?? null;
            $fieldType = $filter['fieldType'] ?? 'text';
            $operator = $filter['operator'];
            $rangeValue = $filter['rangeValue'] ?? null;

            switch ($fieldType) {
                case 'text':
                    switch ($operator) {
                        case 'contains':
                            $query->where($field, 'like', "%{$value}%");
                            break;
                        case 'notContains':
                            $query->where($field, 'not like', "%{$value}%");
                            break;
                        case 'equals':
                            $query->where($field, '=', $value);
                            break;
                        case 'notEqual':
                            $query->where($field, '<>', $value);
                            break;
                        case 'startsWith':
                            $query->where($field, 'like', "{$value}%");
                            break;
                        case 'endsWith':
                            $query->where($field, 'like', "%{$value}");
                            break;
                        case 'blank':
                            $query->whereNull($field);
                            break;
                        case 'notBlank':
                            $query->whereNotNull($field);
                            break;
                        default:
                            throw new Exception("Unsupported operator for text: {$operator}");
                    }
                    break;

                case 'date':
                    switch ($operator) {
                        case 'equals':
                            $query->whereDate($field, '=', $value);
                            break;
                        case 'notEqual':
                            $query->whereDate($field, '<>', $value);
                            break;
                        case 'greaterThan':
                            $query->whereDate($field, '>', $value);
                            break;
                        case 'greaterThanOrEqual':
                            $query->whereDate($field, '>=', $value);
                            break;
                        case 'lessThan':
                            $query->whereDate($field, '<', $value);
                            break;
                        case 'lessThanOrEqual':
                            $query->whereDate($field, '<=', $value);
                            break;
                        case 'inRange':
                            if (!$rangeValue) {
                                throw new Exception("Range value must be provided for inRange operator on date field.");
                            }
                            $query->whereDate($field, '>=', $value)
                                ->whereDate($field, '<=', $rangeValue);
                            break;
                        case 'blank':
                            $query->whereNull($field);
                            break;
                        case 'notBlank':
                            $query->whereNotNull($field);
                            break;
                        default:
                            throw new Exception("Unsupported operator for date: {$operator}");
                    }
                    break;

                case 'number':
                    switch ($operator) {
                        case 'equals':
                            $query->where($field, '=', $value);
                            break;
                        case 'notEqual':
                            $query->where($field, '<>', $value);
                            break;
                        case 'greaterThan':
                            $query->where($field, '>', $value);
                            break;
                        case 'greaterThanOrEqual':
                            $query->where($field, '>=', $value);
                            break;
                        case 'lessThan':
                            $query->where($field, '<', $value);
                            break;
                        case 'lessThanOrEqual':
                            $query->where($field, '<=', $value);
                            break;
                        case 'inRange':
                            if (!$rangeValue) {
                                throw new Exception("Range value must be provided for inRange operator on number field.");
                            }
                            $query->where($field, '>=', $value)
                                ->where($field, '<=', $rangeValue);
                            break;
                        case 'blank':
                            $query->whereNull($field);
                            break;
                        case 'notBlank':
                            $query->whereNotNull($field);
                            break;
                        default:
                            throw new Exception("Unsupported operator for number: {$operator}");
                    }
                    break;

                case 'boolean':
                    $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($operator === 'equals') {
                        $query->where($field, '=', $boolValue);
                    } elseif ($operator === 'notEqual') {
                        $query->where($field, '<>', $boolValue);
                    } else {
                        throw new Exception("Unsupported operator for boolean: {$operator}");
                    }
                    break;

                case 'set':
                    // For sets we can assume equals or contains.
                    if ($operator === 'equals') {
                        $query->where($field, '=', $value);
                    } elseif ($operator === 'contains') {
                        // Assuming value is a comma-separated list.
                        $values = is_array($value) ? $value : explode(',', $value);
                        $query->whereIn($field, $values);
                    } else {
                        throw new Exception("Unsupported operator for set: {$operator}");
                    }
                    break;

                case 'array':
                    // For arrays, we can use a JSON search.
                    $query->where($field, 'like', '%' . $value . '%');
                    break;

                default:
                    throw new Exception("Unsupported field type: {$fieldType}");
            }
        }
        return $query;
    }

    /**
     * Find a model by its ID.
     *
     * @param mixed $id
     * @return mixed|null
     */
    public function findById($id)
    {
        return $this->model::find($id);
    }

    /**
     * Create a new model instance with the given data.
     *
     * @param array $data
     * @return mixed
     */
    public function create(array $data)
    {
        return $this->model::create($data);
    }

    /**
     * Update an existing model instance by its ID.
     *
     * @param mixed $id
     * @param array $data
     * @return mixed|null
     */
    public function update($id, array $data)
    {
        $instance = $this->model::find($id);
        if (!$instance) {
            return null;
        }
        $instance->update($data);
        return $instance;
    }

    /**
     * Delete a model instance by its ID.
     *
     * @param mixed $id
     * @return bool
     */
    public function delete($id): bool
    {
        $instance = $this->model::find($id);
        if (!$instance) {
            return false;
        }
        return $instance->delete();
    }
}
