<?php

namespace App\Repositories;

use App\Repositories\Contracts\RepositoryInterface;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

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
     * @throws InvalidArgumentException
     */
    public function all(array $params = []): LengthAwarePaginator
    {
        // 1) Start with a fresh query
        $query = $this->model->newQuery();

        // 2) Determine how many items per page (default = 10)
        $limit = $params['limit'] ?? 10;
        unset($params['limit']);

        // 3) Dynamically fetch the allowed columns from the table schema
        $columns = $this->model->getConnection()
            ->getSchemaBuilder()
            ->getColumnListing($this->model->getTable());

        // 4) Apply sorting if requested
        if (isset($params['sort_by'])) {
            // Check if the column is valid
            if (!in_array($params['sort_by'], $columns)) {
                throw new InvalidArgumentException("Invalid sort field: {$params['sort_by']}");
            }

            $direction = $params['sort_dir'] ?? 'asc';
            $query->orderBy($params['sort_by'], $direction);

            unset($params['sort_by'], $params['sort_dir']);
        }

        // 5) Apply filters (either under the "filters" key or direct $params)
        if (isset($params['filters']) && is_array($params['filters'])) {
            $query = $this->applyFilters($query, $params['filters'], $columns);
        } else {
            $query = $this->applyFilters($query, $params, $columns);
        }

        // 6) Paginate and return
        return $query->paginate($limit);
    }

    /**
     * Apply a set of dynamic filters to the query.
     *
     * Each filter should be an associative array with keys:
     *   'field' => (string) the column name
     *   'value' => (mixed)  the value to compare
     *   'fieldType' => 'text','date','number','boolean','set','array'
     *   'operator' => 'equals','contains','greaterThan','inRange', etc.
     *   'rangeValue' => used for 'inRange' operator
     *
     * @param  Builder  $query
     * @param  array    $filters
     * @param  array    $columns  The valid table columns
     * @return Builder
     * @throws Exception
     */
    protected function applyFilters(Builder $query, array $filters, array $columns): Builder
    {
        foreach ($filters as $filter) {
            // Ensure 'field' and 'operator' exist
            if (!isset($filter['field'], $filter['operator'])) {
                continue; // or throw an exception if you prefer
            }

            $field = $filter['field'];
            $value = $filter['value'] ?? null;
            $fieldType = $filter['fieldType'] ?? 'text';
            $operator = $filter['operator'];
            $rangeValue = $filter['rangeValue'] ?? null;

            // 1) Skip or reject invalid fields
            if (!in_array($field, $columns)) {
                // either ignore or throw an exception
                throw new InvalidArgumentException("Invalid filter field: {$field}");
            }

            // 2) Apply filtering logic
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
                            $query->whereNotNull($field)->where($field, '!=', '');
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
                                throw new Exception("Range value must be provided for 'inRange' operator on date field.");
                            }
                            $query->whereDate($field, '>=', $value)
                                ->whereDate($field, '<=', $rangeValue);
                            break;
                        case 'blank':
                            $query->whereNull($field);
                            break;
                        case 'notBlank':
                            $query->whereNotNull($field)->where($field, '!=', '');
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
                                throw new Exception("Range value must be provided for 'inRange' operator on number field.");
                            }
                            $query->where($field, '>=', $value)
                                ->where($field, '<=', $rangeValue);
                            break;
                        case 'blank':
                            $query->whereNull($field);
                            break;
                        case 'notBlank':
                            $query->whereNotNull($field)->where($field, '!=', '');
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
                    // For sets, we might have 'equals' or 'contains' meaning an IN clause
                    if ($operator === 'equals') {
                        $query->where($field, '=', $value);
                    } elseif ($operator === 'contains') {
                        // expecting an array or comma-delimited string
                        $values = is_array($value) ? $value : explode(',', $value);
                        $query->whereIn($field, $values);
                    } else {
                        throw new Exception("Unsupported operator for set: {$operator}");
                    }
                    break;

                case 'array':
                    // Searching for a value inside a JSON column or similar
                    // This is very application-specific. For demonstration:
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
    public function findById($id): mixed
    {
        return $this->model->find($id);
    }

    /**
     * Create a new model instance with the given data.
     *
     * @param array $data
     * @return mixed
     */
    public function create(array $data): mixed
    {
        return $this->model->create($data);
    }

    /**
     * Update an existing model instance by its ID.
     *
     * @param mixed $id
     * @param array $data
     * @return mixed|null
     */
    public function update($id, array $data): mixed
    {
        $instance = $this->model->find($id);
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
        $instance = $this->model->find($id);
        if (!$instance) {
            return false;
        }

        return (bool) $instance->delete();
    }
}
