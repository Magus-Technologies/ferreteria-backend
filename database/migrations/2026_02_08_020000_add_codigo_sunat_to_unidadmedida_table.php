<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('unidadmedida', function (Blueprint $table) {
            $table->string('codigo_sunat', 10)->nullable()->after('name')->comment('Código de unidad según catálogo 03 SUNAT');
        });

        // Actualizar códigos SUNAT para unidades comunes
        // Catálogo 03 - Unidades de medida SUNAT
        // https://cpe.sunat.gob.pe/sites/default/files/inline-files/Catalogo_03_0.xls
        
        $unidadesSunat = [
            // Unidades de longitud
            'METRO' => 'MTR',
            'METROS' => 'MTR',
            'CENTIMETRO' => 'CMT',
            'CENTIMETROS' => 'CMT',
            'MILIMETRO' => 'MMT',
            'MILIMETROS' => 'MMT',
            'KILOMETRO' => 'KMT',
            'KILOMETROS' => 'KMT',
            'PULGADA' => 'INH',
            'PULGADAS' => 'INH',
            'PIE' => 'FOT',
            'PIES' => 'FOT',
            
            // Unidades de peso
            'KILOGRAMO' => 'KGM',
            'KILOGRAMOS' => 'KGM',
            'KG' => 'KGM',
            'GRAMO' => 'GRM',
            'GRAMOS' => 'GRM',
            'TONELADA' => 'TNE',
            'TONELADAS' => 'TNE',
            'LIBRA' => 'LBR',
            'LIBRAS' => 'LBR',
            'ONZA' => 'ONZ',
            'ONZAS' => 'ONZ',
            
            // Unidades de volumen
            'LITRO' => 'LTR',
            'LITROS' => 'LTR',
            'GALON' => 'GLL',
            'GALONES' => 'GLL',
            'MILILITRO' => 'MLT',
            'MILILITROS' => 'MLT',
            'METRO CUBICO' => 'MTQ',
            'METROS CUBICOS' => 'MTQ',
            'M3' => 'MTQ',
            
            // Unidades de área
            'METRO CUADRADO' => 'MTK',
            'METROS CUADRADOS' => 'MTK',
            'M2' => 'MTK',
            
            // Unidades de cantidad
            'UNIDAD' => 'NIU',
            'UNIDADES' => 'NIU',
            'UND' => 'NIU',
            'PIEZA' => 'NIU',
            'PIEZAS' => 'NIU',
            'CAJA' => 'BX',
            'CAJAS' => 'BX',
            'PAQUETE' => 'PK',
            'PAQUETES' => 'PK',
            'BOLSA' => 'BG',
            'BOLSAS' => 'BG',
            'SACO' => 'SA',
            'SACOS' => 'SA',
            'DOCENA' => 'DZN',
            'DOCENAS' => 'DZN',
            'CIENTO' => 'CEN',
            'CIENTOS' => 'CEN',
            'MILLAR' => 'MIL',
            'MILLARES' => 'MIL',
            'PAR' => 'PR',
            'PARES' => 'PR',
            'JUEGO' => 'SET',
            'JUEGOS' => 'SET',
            'ROLLO' => 'RO',
            'ROLLOS' => 'RO',
            'TAMBOR' => 'DR',
            'TAMBORES' => 'DR',
            'BIDON' => 'JR',
            'BIDONES' => 'JR',
            'LATA' => 'CA',
            'LATAS' => 'CA',
            'BALDE' => 'BJ',
            'BALDES' => 'BJ',
            'CUBO' => 'BJ', // Balde/Cubo
            'CUBOS' => 'BJ',
            'BARRIL' => 'BA',
            'BARRILES' => 'BA',
            'PLANCHA' => 'SHT',
            'PLANCHAS' => 'SHT',
            'HOJA' => 'SHT',
            'HOJAS' => 'SHT',
            'TUBO' => 'TU',
            'TUBOS' => 'TU',
            'VARA' => 'YRD',
            'VARAS' => 'YRD',
            
            // Unidades de tiempo
            'HORA' => 'HUR',
            'HORAS' => 'HUR',
            'DIA' => 'DAY',
            'DIAS' => 'DAY',
            'MES' => 'MON',
            'MESES' => 'MON',
            'AÑO' => 'ANN',
            'AÑOS' => 'ANN',
            
            // Servicios
            'SERVICIO' => 'ZZ',
            'SERVICIOS' => 'ZZ',
            'GLOBAL' => 'ZZ',
        ];

        foreach ($unidadesSunat as $nombre => $codigoSunat) {
            DB::table('unidadmedida')
                ->where('name', 'LIKE', $nombre)
                ->update(['codigo_sunat' => $codigoSunat]);
        }

        // Por defecto, las unidades sin código específico se marcan como NIU (Unidad)
        DB::table('unidadmedida')
            ->whereNull('codigo_sunat')
            ->update(['codigo_sunat' => 'NIU']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unidadmedida', function (Blueprint $table) {
            $table->dropColumn('codigo_sunat');
        });
    }
};
