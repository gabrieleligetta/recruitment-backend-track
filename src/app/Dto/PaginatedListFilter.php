<?php

namespace App\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "PaginatedListFilter",
    title: "Paginated List Filter",
    description: "Parameters for filtering, sorting, and pagination.",
    required: ["filters", "sort", "limit"],
    properties: [
        new OA\Property(
            property: "filters",
            description: "Array of filter objects",
            type: "array",
            items: new OA\Items(ref: "#/components/schemas/Filter")
        ),
        new OA\Property(
            property: "sort",
            ref: "#/components/schemas/Sort"
        ),
        new OA\Property(
            property: "limit",
            description: "Maximum number of records to return",
            type: "integer",
            example: 15
        )
    ]
)]
class PaginatedListFilter
{
    // This DTO is for documentation purposes only.
}
