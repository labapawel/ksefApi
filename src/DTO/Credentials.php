<?php

declare(strict_types=1);

namespace Labap\KsefApi\DTO;

final class Credentials
{
    public function __construct(
        public readonly string $nip,
        public readonly string $ksefToken,
        public readonly string $certificatePath,
        public readonly string $privateKeyPath,
        public readonly string $certificatePassword,
    ) {
    }
}
