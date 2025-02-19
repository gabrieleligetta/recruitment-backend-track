<?php

namespace App\Dto;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Filter",
    title: "Filter",
    description: "A single filter condition.",
    required: ["field", "operator"],
    properties: [
        new OA\Property(
            property: "field",
            description: "Field name to filter on.",
            type: "string",
            example: "user_id"
        ),
        new OA\Property(
            property: "value",
            description: "Value to filter by.",
            type: "string",
            example: "36"
        ),
        new OA\Property(
            property: "fieldType",
            description: "Type of the field.",
            type: "string",
            enum: ["text", "date", "number", "boolean", "set", "array"],
            example: "number"
        ),
        new OA\Property(
            property: "operator",
            description: "Operator for the filter condition.",
            type: "string",
            enum: [
                "contains", "notContains", "equals", "notEqual", "startsWith",
                "endsWith", "blank", "notBlank", "greaterThan", "greaterThanOrEqual",
                "lessThan", "lessThanOrEqual", "inRange"
            ],
            example: "equals"
        ),
        new OA\Property(
            property: "rangeValue",
            description: "Secondary value for range filters (if applicable).",
            type: "string",
            example: "50",
            nullable: true
        )
    ]
)]
class Filter
{
    // This DTO is only for documentation purposes.
}
