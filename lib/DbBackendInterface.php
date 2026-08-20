<?php
declare(strict_types=1);

/** Kontrak storan data — dilaksanakan oleh JsonFileBackend dan FirestoreBackend. */
interface DbBackendInterface
{
    public function all(): array;

    /** Ubah data dalam callback (terima array by-reference); hasil ditulis semula. */
    public function mutate(callable $fn);
}
