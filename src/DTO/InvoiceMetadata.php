<?php

declare(strict_types=1);

namespace Labap\KsefApi\DTO;

final class InvoiceMetadata
{
    public function __construct(
        public readonly ?string $invoiceNumber,
        public readonly ?string $invoiceDate,
        public readonly ?string $sellerNip,
        public readonly ?string $sellerName,
        public readonly ?string $buyerNip,
        public readonly ?string $buyerName,
        public readonly bool $isSale,
    ) {
    }
}
