<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    /**
     * Nazwa tabeli bazy danych.
     *
     * @var string
     */
    protected $table = 'ksef_invoices';

    /**
     * Atrybuty które mogą być masowo przypisane.
     *
     * @var list<string>
     */
    protected $fillable = [
        'direction',
        'invoice_number',
        'invoice_date',
        'seller_nip',
        'seller_name',
        'buyer_nip',
        'buyer_name',
        'environment',
        'ksef_number',
        'session_id',
        'reference_number',
        'status',
        'is_signed',
        'xml_encrypted',
        'xml_hash',
        'signature_encrypted',
        'meta',
        'error_details',
        'processed_at',
        'submitted_at',
    ];

    /**
     * Atrybuty które powinny być konwertowane do natywnych typów.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'invoice_date' => 'date',
        'is_signed' => 'boolean',
        'processed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'meta' => 'json',
        'error_details' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Atrybuty które powinny być zaszyfrowane.
     *
     * @var list<string>
     */
    protected $encrypted = [
        'xml_encrypted',
        'signature_encrypted',
    ];

    // Stałe dla kierunków
    public const DIRECTION_SALE = 'sale';
    public const DIRECTION_PURCHASE = 'purchase';

    // Stałe dla statusów
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Filtruj faktury po kierunku.
     *
     * @param Builder<Invoice> $query
     * @param string $direction
     * @return Builder<Invoice>
     */
    public function scopeDirection(Builder $query, string $direction): Builder
    {
        return $query->where('direction', $direction);
    }

    /**
     * Filtruj faktury sprzedane.
     *
     * @param Builder<Invoice> $query
     * @return Builder<Invoice>
     */
    public function scopeSale(Builder $query): Builder
    {
        return $query->direction(self::DIRECTION_SALE);
    }

    /**
     * Filtruj faktury zakupione.
     *
     * @param Builder<Invoice> $query
     * @return Builder<Invoice>
     */
    public function scopePurchase(Builder $query): Builder
    {
        return $query->direction(self::DIRECTION_PURCHASE);
    }

    /**
     * Filtruj faktury po statusie.
     *
     * @param Builder<Invoice> $query
     * @param string $status
     * @return Builder<Invoice>
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Filtruj oczekujące faktury.
     *
     * @param Builder<Invoice> $query
     * @return Builder<Invoice>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->status(self::STATUS_PENDING);
    }

    /**
     * Filtruj faktury w trakcie przetwarzania.
     *
     * @param Builder<Invoice> $query
     * @return Builder<Invoice>
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->status(self::STATUS_PROCESSING);
    }

    /**
     * Filtruj zaakceptowane faktury.
     *
     * @param Builder<Invoice> $query
     * @return Builder<Invoice>
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->status(self::STATUS_ACCEPTED);
    }

    /**
     * Filtruj odrzucone faktury.
     *
     * @param Builder<Invoice> $query
     * @return Builder<Invoice>
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->status(self::STATUS_REJECTED);
    }

    /**
     * Filtruj faktury po NIP sprzedawcy.
     *
     * @param Builder<Invoice> $query
     * @param string $nip
     * @return Builder<Invoice>
     */
    public function scopeSellerNip(Builder $query, string $nip): Builder
    {
        return $query->where('seller_nip', $nip);
    }

    /**
     * Filtruj faktury po NIP nabywcy.
     *
     * @param Builder<Invoice> $query
     * @param string $nip
     * @return Builder<Invoice>
     */
    public function scopeBuyerNip(Builder $query, string $nip): Builder
    {
        return $query->where('buyer_nip', $nip);
    }

    /**
     * Filtruj faktury po numerze KSeF.
     *
     * @param Builder<Invoice> $query
     * @param string $ksefNumber
     * @return Builder<Invoice>
     */
    public function scopeKsefNumber(Builder $query, string $ksefNumber): Builder
    {
        return $query->where('ksef_number', $ksefNumber);
    }

    /**
     * Sprawdź czy faktura została już przetworzenia przez KSeF.
     *
     * @return bool
     */
    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    /**
     * Sprawdź czy faktura czeka na przetwarzanie.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Sprawdź czy faktura została zaakceptowana przez KSeF.
     *
     * @return bool
     */
    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * Sprawdź czy faktura została odrzucona.
     *
     * @return bool
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}
