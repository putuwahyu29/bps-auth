<?php

namespace Bps\Sso\DTO;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class PegawaiData implements Arrayable, JsonSerializable
{
    protected string $username;
    protected array $attributes;
    protected array $raw;

    public function __construct(array $data)
    {
        $this->raw = $data;
        $this->username = $data['username'] ?? '';
        $this->attributes = $data['attributes'] ?? [];
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Ambil nilai atribut pegawai berdasarkan key.
     * Jika nilai dalam bentuk array, secara default mengembalikan elemen pertama.
     */
    public function getAttribute(string $key, mixed $default = null, bool $asArray = false): mixed
    {
        if (!array_key_exists($key, $this->attributes)) {
            return $default;
        }

        $val = $this->attributes[$key];

        if ($asArray) {
            return is_array($val) ? $val : [$val];
        }

        if (is_array($val)) {
            return $val[0] ?? $default;
        }

        return $val;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getName(): ?string
    {
        return $this->getAttribute('nama')
            ?? $this->getAttribute('name')
            ?? $this->getAttribute('nama_lengkap')
            ?? $this->username;
    }

    public function getNama(): ?string
    {
        return $this->getName();
    }

    public function getEmail(): ?string
    {
        return $this->getAttribute('email');
    }

    public function getNipLama(): ?string
    {
        return $this->getAttribute('niplama')
            ?? $this->getAttribute('nip-lama')
            ?? $this->getAttribute('nip_lama')
            ?? $this->getAttribute('nip');
    }

    public function getNip(): ?string
    {
        return $this->getNipLama();
    }

    public function getNipBaru(): ?string
    {
        return $this->getAttribute('nipbaru')
            ?? $this->getAttribute('nip_baru')
            ?? $this->getNip();
    }

    public function getJabatan(): ?string
    {
        return $this->getAttribute('jabatan');
    }

    public function getUnit(): ?string
    {
        return $this->getAttribute('unit')
            ?? $this->getAttribute('kodeunitorganisasi')
            ?? $this->getAttribute('kode_unit');
    }

    public function getRaw(): array
    {
        return $this->raw;
    }

    public function toArray(): array
    {
        return [
            'username' => $this->getUsername(),
            'name' => $this->getName(),
            'email' => $this->getEmail(),
            'nip' => $this->getNip(),
            'nip_baru' => $this->getNipBaru(),
            'jabatan' => $this->getJabatan(),
            'unit' => $this->getUnit(),
            'attributes' => $this->getAttributes(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
