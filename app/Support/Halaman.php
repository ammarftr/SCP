<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Pembantu paginasi untuk data yang sudah berupa Collection (hasil gabungan /
 * pengelompokan di PHP), sehingga tidak bisa memakai ->paginate() milik query.
 */
class Halaman
{
    public static function dari(Collection $item, int $perHalaman, string $namaHalaman = 'page', ?string $fragment = null): LengthAwarePaginator
    {
        $request = request();
        $sekarang = max(1, LengthAwarePaginator::resolveCurrentPage($namaHalaman));

        $paginator = new LengthAwarePaginator(
            $item->forPage($sekarang, $perHalaman)->values(),
            $item->count(),
            $perHalaman,
            $sekarang,
            ['path' => $request->url(), 'pageName' => $namaHalaman]
        );

        $paginator->appends($request->except($namaHalaman));

        return $fragment ? $paginator->fragment($fragment) : $paginator;
    }
}
