<?php

namespace Bps\Sso\Tests\Unit;

use Bps\Sso\DTO\PegawaiData;
use PHPUnit\Framework\TestCase;

class PegawaiDataTest extends TestCase
{
    public function test_it_handles_array_attributes_and_fallbacks(): void
    {
        $data = [
            'username' => 'dewi.lestari',
            'attributes' => [
                'nama' => ['Dewi Lestari, M.Stat.'],
                'email' => ['dewi@bps.go.id'],
                'nip' => ['340056734'],
                'nipbaru' => ['199001012015012001'],
                'jabatan' => ['Statistisi Ahli Pertama'],
                'unit' => ['34000'],
                'hobby' => ['Coding', 'Reading'],
            ],
        ];

        $pegawai = new PegawaiData($data);

        $this->assertEquals('dewi.lestari', $pegawai->getUsername());
        $this->assertEquals('Dewi Lestari, M.Stat.', $pegawai->getName());
        $this->assertEquals('Dewi Lestari, M.Stat.', $pegawai->getNama());
        $this->assertEquals('dewi@bps.go.id', $pegawai->getEmail());
        $this->assertEquals('340056734', $pegawai->getNip());
        $this->assertEquals('199001012015012001', $pegawai->getNipBaru());
        $this->assertEquals('Statistisi Ahli Pertama', $pegawai->getJabatan());
        $this->assertEquals('34000', $pegawai->getUnit());

        // Multi-value attribute as array
        $this->assertEquals(['Coding', 'Reading'], $pegawai->getAttribute('hobby', asArray: true));
        $this->assertEquals('Coding', $pegawai->getAttribute('hobby'));

        // Non-existent attribute with default
        $this->assertEquals('default_val', $pegawai->getAttribute('non_existent', 'default_val'));

        // toArray
        $array = $pegawai->toArray();
        $this->assertIsArray($array);
        $this->assertEquals('dewi.lestari', $array['username']);
        $this->assertEquals('Dewi Lestari, M.Stat.', $array['name']);
    }
}
