<?php

namespace App\Services;

use App\Exceptions\GreenterException;
use App\Services\Interfaces\GreenterServiceInterface;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Company;
use Greenter\Model\Company\Address;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;
use Greenter\See;
use Greenter\Ws\Services\SunatEndpoints;

class GreenterService implements GreenterServiceInterface
{
    private ?See $see = null;
    private bool $modoSimulacion;

    public function __construct()
    {
        // Modo simulación si no existe el certificado
        $certificatePath = config('greenter.certificate_path');
        $this->modoSimulacion = !file_exists($certificatePath);

        if (!$this->modoSimulacion) {
            $this->see = new See();
            $this->see->setService(SunatEndpoints::FE_BETA);
            $this->see->setClaveSOL(
                config('greenter.ruc'),
                config('greenter.sol_user'),
                config('greenter.sol_pass')
            );
            $this->see->setCertificate(file_get_contents($certificatePath));
        }
    }

    /**
     * Generar XML y enviar nota de débito a SUNAT
     */
    public function generarYEnviarNotaDebito(array $data): array
    {
        try {
            // MODO SIMULACIÓN - Sin certificado digital
            if ($this->modoSimulacion) {
                return $this->simularNotaDebito($data);
            }

            // MODO REAL - Con certificado digital
            $note = $this->construirNotaDebito($data);
            $result = $this->see->send($note);

            if (!$result->isSuccess()) {
                $error = $result->getError();
                throw GreenterException::errorEnviandoSunat(
                    $error ? $error->getMessage() : 'Error desconocido'
                );
            }

            return [
                'success' => true,
                'xml' => $this->see->getFactory()->getLastXml(),
                'cdr' => $result->getCdrZip(),
                'hash_cpe' => hash('sha256', $this->see->getFactory()->getLastXml()),
                'hash_cdr' => $result->getCdrResponse() ? $result->getCdrResponse()->getDigestValue() : null,
                'codigo_sunat' => $result->getCdrResponse() ? $result->getCdrResponse()->getCode() : null,
                'mensaje_sunat' => $result->getCdrResponse() ? $result->getCdrResponse()->getDescription() : null,
                'modo' => 'PRODUCCION',
            ];
        } catch (GreenterException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw GreenterException::errorGenerandoXml($e->getMessage());
        }
    }

    /**
     * Generar solo el XML de la nota de débito (sin enviar)
     */
    public function generarXmlNotaDebito(array $data): string
    {
        try {
            if ($this->modoSimulacion) {
                return $this->generarXmlSimulado($data);
            }

            $note = $this->construirNotaDebito($data);
            return $this->see->getXmlSigned($note);
        } catch (\Exception $e) {
            throw GreenterException::errorGenerandoXml($e->getMessage());
        }
    }

    /**
     * Generar XML y enviar nota de crédito a SUNAT
     */
    public function generarYEnviarNotaCredito(array $data): array
    {
        try {
            // MODO SIMULACIÓN - Sin certificado digital
            if ($this->modoSimulacion) {
                return $this->simularNotaCredito($data);
            }

            // MODO REAL - Con certificado digital
            $note = $this->construirNotaCredito($data);
            $result = $this->see->send($note);

            if (!$result->isSuccess()) {
                $error = $result->getError();
                throw GreenterException::errorEnviandoSunat(
                    $error ? $error->getMessage() : 'Error desconocido'
                );
            }

            return [
                'success' => true,
                'xml' => $this->see->getFactory()->getLastXml(),
                'cdr' => $result->getCdrZip(),
                'hash_cpe' => hash('sha256', $this->see->getFactory()->getLastXml()),
                'hash_cdr' => $result->getCdrResponse() ? $result->getCdrResponse()->getDigestValue() : null,
                'codigo_sunat' => $result->getCdrResponse() ? $result->getCdrResponse()->getCode() : null,
                'mensaje_sunat' => $result->getCdrResponse() ? $result->getCdrResponse()->getDescription() : null,
                'modo' => 'PRODUCCION',
            ];
        } catch (GreenterException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw GreenterException::errorGenerandoXml($e->getMessage());
        }
    }

    /**
     * Generar solo el XML de la nota de crédito (sin enviar)
     */
    public function generarXmlNotaCredito(array $data): string
    {
        try {
            if ($this->modoSimulacion) {
                return $this->generarXmlSimulado($data, '07');
            }

            $note = $this->construirNotaCredito($data);
            return $this->see->getXmlSigned($note);
        } catch (\Exception $e) {
            throw GreenterException::errorGenerandoXml($e->getMessage());
        }
    }

    /**
     * Generar XML y enviar factura/boleta a SUNAT
     */
    public function generarYEnviarFactura(array $data): array
    {
        try {
            // MODO SIMULACIÓN - Sin certificado digital
            if ($this->modoSimulacion) {
                return $this->simularFactura($data);
            }

            // MODO REAL - Con certificado digital
            $invoice = $this->construirFactura($data);
            $result = $this->see->send($invoice);

            if (!$result->isSuccess()) {
                $error = $result->getError();
                throw GreenterException::errorEnviandoSunat(
                    $error ? $error->getMessage() : 'Error desconocido'
                );
            }

            return [
                'success' => true,
                'xml' => $this->see->getFactory()->getLastXml(),
                'cdr' => $result->getCdrZip(),
                'hash_cpe' => hash('sha256', $this->see->getFactory()->getLastXml()),
                'hash_cdr' => $result->getCdrResponse() ? $result->getCdrResponse()->getDigestValue() : null,
                'codigo_sunat' => $result->getCdrResponse() ? $result->getCdrResponse()->getCode() : null,
                'mensaje_sunat' => $result->getCdrResponse() ? $result->getCdrResponse()->getDescription() : null,
                'modo' => 'PRODUCCION',
            ];
        } catch (GreenterException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw GreenterException::errorGenerandoXml($e->getMessage());
        }
    }

    /**
     * Generar solo el XML de la factura/boleta (sin enviar a SUNAT)
     */
    public function generarXmlFactura(array $data): string
    {
        try {
            if ($this->modoSimulacion) {
                return $this->generarXmlSimulado($data, $data['tipo_doc']);
            }

            $invoice = $this->construirFactura($data);
            return $this->see->getXmlSigned($invoice);
        } catch (\Exception $e) {
            throw GreenterException::errorGenerandoXml($e->getMessage());
        }
    }

    /**
     * Construir objeto Note de Greenter
     */
    private function construirNotaDebito(array $data): Note
    {
        $company = $this->crearEmpresa();
        $client = $this->crearCliente($data['cliente']);

        $note = new Note();
        $note
            ->setUblVersion('2.1')
            ->setTipoDoc('08') // 08 = Nota de Débito
            ->setSerie($data['serie'])
            ->setCorrelativo($data['numero'])
            ->setFechaEmision(new \DateTime($data['fecha']))
            ->setTipDocAfectado($data['tipo_doc_afectado'])
            ->setNumDocfectado($data['num_doc_afectado'])
            ->setCodMotivo($data['cod_motivo'])
            ->setDesMotivo($data['des_motivo'])
            ->setTipoMoneda($data['tipo_moneda'] ?? 'PEN')
            ->setCompany($company)
            ->setClient($client)
            ->setMtoOperGravadas($data['mto_oper_gravadas'])
            ->setMtoIGV($data['mto_igv'])
            ->setTotalImpuestos($data['mto_igv'])
            ->setMtoImpVenta($data['total']);

        // Agregar items
        $items = [];
        foreach ($data['items'] as $index => $item) {
            $detail = new SaleDetail();
            $detail
                ->setCodProducto($item['codigo'])
                ->setUnidad($item['unidad'])
                ->setCantidad($item['cantidad'])
                ->setDescripcion($item['descripcion'])
                ->setMtoBaseIgv($item['mto_base_igv'])
                ->setPorcentajeIgv(18.00)
                ->setIgv($item['igv'])
                ->setTipAfeIgv('10')
                ->setTotalImpuestos($item['igv'])
                ->setMtoValorVenta($item['valor_venta'])
                ->setMtoValorUnitario($item['valor_unitario'])
                ->setMtoPrecioUnitario($item['precio_unitario']);
            
            $items[] = $detail;
        }
        $note->setDetails($items);

        // Agregar leyenda (monto en letras)
        $legend = new Legend();
        $legend
            ->setCode('1000')
            ->setValue($data['monto_en_letras']);
        $note->setLegends([$legend]);

        return $note;
    }

    /**
     * Construir objeto Note de Greenter para Nota de Crédito
     */
    private function construirNotaCredito(array $data): Note
    {
        $company = $this->crearEmpresa();
        $client = $this->crearCliente($data['cliente']);

        $note = new Note();
        $note
            ->setUblVersion('2.1')
            ->setTipoDoc('07') // 07 = Nota de Crédito
            ->setSerie($data['serie'])
            ->setCorrelativo($data['numero'])
            ->setFechaEmision(new \DateTime($data['fecha']))
            ->setTipDocAfectado($data['tipo_doc_afectado'])
            ->setNumDocfectado($data['num_doc_afectado'])
            ->setCodMotivo($data['cod_motivo'])
            ->setDesMotivo($data['des_motivo'])
            ->setTipoMoneda($data['tipo_moneda'] ?? 'PEN')
            ->setCompany($company)
            ->setClient($client)
            ->setMtoOperGravadas($data['mto_oper_gravadas'])
            ->setMtoIGV($data['mto_igv'])
            ->setTotalImpuestos($data['mto_igv'])
            ->setMtoImpVenta($data['total']);

        // Agregar items
        $items = [];
        foreach ($data['items'] as $index => $item) {
            $detail = new SaleDetail();
            $detail
                ->setCodProducto($item['codigo'])
                ->setUnidad($item['unidad'])
                ->setCantidad($item['cantidad'])
                ->setDescripcion($item['descripcion'])
                ->setMtoBaseIgv($item['mto_base_igv'])
                ->setPorcentajeIgv(18.00)
                ->setIgv($item['igv'])
                ->setTipAfeIgv('10')
                ->setTotalImpuestos($item['igv'])
                ->setMtoValorVenta($item['valor_venta'])
                ->setMtoValorUnitario($item['valor_unitario'])
                ->setMtoPrecioUnitario($item['precio_unitario']);
            
            $items[] = $detail;
        }
        $note->setDetails($items);

        // Agregar leyenda (monto en letras)
        $legend = new Legend();
        $legend
            ->setCode('1000')
            ->setValue($data['monto_en_letras']);
        $note->setLegends([$legend]);

        return $note;
    }

    /**
     * Construir objeto Invoice de Greenter para Factura/Boleta
     */
    private function construirFactura(array $data)
    {
        $company = $this->crearEmpresa();
        $client = $this->crearCliente($data['cliente']);

        $invoice = new \Greenter\Model\Sale\Invoice();
        $invoice
            ->setUblVersion('2.1')
            ->setTipoDoc($data['tipo_doc']) // 01=Factura, 03=Boleta
            ->setSerie($data['serie'])
            ->setCorrelativo($data['numero'])
            ->setFechaEmision(new \DateTime($data['fecha']))
            ->setTipoMoneda($data['tipo_moneda'] ?? 'PEN')
            ->setCompany($company)
            ->setClient($client)
            ->setMtoOperGravadas($data['mto_oper_gravadas'])
            ->setMtoIGV($data['mto_igv'])
            ->setTotalImpuestos($data['mto_igv'])
            ->setMtoImpVenta($data['total']);

        // Agregar items
        $items = [];
        foreach ($data['items'] as $index => $item) {
            $detail = new SaleDetail();
            $detail
                ->setCodProducto($item['codigo'])
                ->setUnidad($item['unidad'])
                ->setCantidad($item['cantidad'])
                ->setDescripcion($item['descripcion'])
                ->setMtoBaseIgv($item['mto_base_igv'])
                ->setPorcentajeIgv(18.00)
                ->setIgv($item['igv'])
                ->setTipAfeIgv('10')
                ->setTotalImpuestos($item['igv'])
                ->setMtoValorVenta($item['valor_venta'])
                ->setMtoValorUnitario($item['valor_unitario'])
                ->setMtoPrecioUnitario($item['precio_unitario']);
            
            $items[] = $detail;
        }
        $invoice->setDetails($items);

        // Agregar leyenda (monto en letras)
        $legend = new Legend();
        $legend
            ->setCode('1000')
            ->setValue($data['monto_en_letras']);
        $invoice->setLegends([$legend]);

        return $invoice;
    }

    /**
     * Simular Nota de Débito (sin certificado)
     */
    private function simularNotaDebito(array $data): array
    {
        // Generar XML simulado
        $xml = $this->generarXmlSimulado($data, '08');
        
        // Generar hash simulado
        $hashCpe = hash('sha256', $xml);

        // Simular CDR
        $cdr = $this->generarCdrSimulado($data, 'Nota de Débito');

        return [
            'success' => true,
            'modo' => 'SIMULACION',
            'xml' => $xml,
            'cdr' => base64_encode($cdr),
            'hash_cpe' => $hashCpe,
            'hash_cdr' => hash('sha256', $cdr),
            'codigo_sunat' => '0',
            'mensaje_sunat' => 'La Nota de Débito ha sido aceptada (SIMULADO)',
        ];
    }

    /**
     * Simular Nota de Crédito (sin certificado)
     */
    private function simularNotaCredito(array $data): array
    {
        // Generar XML simulado
        $xml = $this->generarXmlSimulado($data, '07');
        
        // Generar hash simulado
        $hashCpe = hash('sha256', $xml);

        // Simular CDR
        $cdr = $this->generarCdrSimulado($data, 'Nota de Crédito');

        return [
            'success' => true,
            'modo' => 'SIMULACION',
            'xml' => $xml,
            'cdr' => base64_encode($cdr),
            'hash_cpe' => $hashCpe,
            'hash_cdr' => hash('sha256', $cdr),
            'codigo_sunat' => '0',
            'mensaje_sunat' => 'La Nota de Crédito ha sido aceptada (SIMULADO)',
        ];
    }

    /**
     * Simular Factura/Boleta (sin certificado)
     */
    private function simularFactura(array $data): array
    {
        // Generar XML simulado
        $xml = $this->generarXmlSimulado($data, $data['tipo_doc']);
        
        // Generar hash simulado
        $hashCpe = hash('sha256', $xml);

        // Simular CDR
        $tipoNombre = $data['tipo_doc'] === '01' ? 'Factura' : 'Boleta';
        $cdr = $this->generarCdrSimulado($data, $tipoNombre);

        return [
            'success' => true,
            'modo' => 'SIMULACION',
            'xml' => $xml,
            'cdr' => base64_encode($cdr),
            'hash_cpe' => $hashCpe,
            'hash_cdr' => hash('sha256', $cdr),
            'codigo_sunat' => '0',
            'mensaje_sunat' => "La {$tipoNombre} ha sido aceptada (SIMULADO)",
        ];
    }

    /**
     * Generar CDR simulado
     */
    private function generarCdrSimulado(array $data, string $tipoNota = 'Nota de Débito'): string
    {
        $cdr = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $cdr .= '<ApplicationResponse xmlns="urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2">' . "\n";
        $cdr .= "  <ResponseCode>0</ResponseCode>\n";
        $cdr .= "  <Description>La {$tipoNota} ha sido aceptada (SIMULADO)</Description>\n";
        $cdr .= "  <DocumentReference>\n";
        $cdr .= "    <ID>{$data['serie']}-{$data['numero']}</ID>\n";
        $cdr .= "  </DocumentReference>\n";
        $cdr .= '</ApplicationResponse>';
        
        return $cdr;
    }

    /**
     * Generar XML simulado para pruebas
     */
    private function generarXmlSimulado(array $data, string $tipoDoc = '08'): string
    {
        $company = config('greenter.razon_social');
        $ruc = config('greenter.ruc');
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">' . "\n";
        $xml .= "  <ID>{$data['serie']}-{$data['numero']}</ID>\n";
        $xml .= "  <IssueDate>{$data['fecha']}</IssueDate>\n";
        $xml .= "  <InvoiceTypeCode>{$tipoDoc}</InvoiceTypeCode>\n";
        $xml .= "  <DocumentCurrencyCode>{$data['tipo_moneda']}</DocumentCurrencyCode>\n";
        $xml .= "  <AccountingSupplierParty>\n";
        $xml .= "    <Party>\n";
        $xml .= "      <PartyIdentification><ID>{$ruc}</ID></PartyIdentification>\n";
        $xml .= "      <PartyName><Name>{$company}</Name></PartyName>\n";
        $xml .= "    </Party>\n";
        $xml .= "  </AccountingSupplierParty>\n";
        $xml .= "  <AccountingCustomerParty>\n";
        $xml .= "    <Party>\n";
        $xml .= "      <PartyIdentification><ID>{$data['cliente']['num_doc']}</ID></PartyIdentification>\n";
        $xml .= "      <PartyName><Name>{$data['cliente']['razon_social']}</Name></PartyName>\n";
        $xml .= "    </Party>\n";
        $xml .= "  </AccountingCustomerParty>\n";
        $xml .= "  <LegalMonetaryTotal>\n";
        $xml .= "    <PayableAmount currencyID=\"{$data['tipo_moneda']}\">{$data['total']}</PayableAmount>\n";
        $xml .= "  </LegalMonetaryTotal>\n";
        
        foreach ($data['items'] as $index => $item) {
            $xml .= "  <InvoiceLine>\n";
            $xml .= "    <ID>" . ($index + 1) . "</ID>\n";
            $xml .= "    <InvoicedQuantity>{$item['cantidad']}</InvoicedQuantity>\n";
            $xml .= "    <LineExtensionAmount>{$item['valor_venta']}</LineExtensionAmount>\n";
            $xml .= "    <Item><Description>{$item['descripcion']}</Description></Item>\n";
            $xml .= "  </InvoiceLine>\n";
        }
        
        $xml .= '</Invoice>';
        
        return $xml;
    }

    /**
     * Crear empresa emisora
     */
    private function crearEmpresa(): Company
    {
        $address = new Address();
        $address
            ->setUbigueo(config('greenter.ubigeo'))
            ->setDepartamento(config('greenter.departamento'))
            ->setProvincia(config('greenter.provincia'))
            ->setDistrito(config('greenter.distrito'))
            ->setUrbanizacion('-')
            ->setDireccion(config('greenter.direccion'))
            ->setCodLocal('0000');

        $company = new Company();
        $company
            ->setRuc(config('greenter.ruc'))
            ->setRazonSocial(config('greenter.razon_social'))
            ->setNombreComercial(config('greenter.nombre_comercial'))
            ->setAddress($address);

        return $company;
    }

    /**
     * Crear cliente
     */
    private function crearCliente(array $data): Client
    {
        $client = new Client();
        $client
            ->setTipoDoc($data['tipo_doc']) // 1=DNI, 6=RUC
            ->setNumDoc($data['num_doc'])
            ->setRznSocial($data['razon_social']);

        if (isset($data['direccion'])) {
            $address = new Address();
            $address->setDireccion($data['direccion']);
            $client->setAddress($address);
        }

        return $client;
    }

    /**
     * Consultar estado de comprobante en SUNAT
     */
    public function consultarEstado(string $ruc, string $tipoDoc, string $serie, string $numero): array
    {
        // MODO SIMULACIÓN
        if ($this->modoSimulacion) {
            return [
                'success' => true,
                'modo' => 'SIMULACION',
                'estado_sunat' => 'aceptado',
                'codigo' => '0',
                'mensaje' => 'Comprobante aceptado (SIMULADO)',
            ];
        }

        // MODO REAL
        try {
            $result = $this->see->getStatus($ruc . '-' . $tipoDoc . '-' . $serie . '-' . $numero);

            return [
                'success' => $result->isSuccess(),
                'estado_sunat' => $result->isSuccess() ? 'aceptado' : 'rechazado',
                'codigo' => $result->getCdrResponse() ? $result->getCdrResponse()->getCode() : null,
                'mensaje' => $result->getCdrResponse() ? $result->getCdrResponse()->getDescription() : null,
                'error' => $result->getError() ? $result->getError()->getMessage() : null,
            ];
        } catch (\Exception $e) {
            throw GreenterException::errorEnviandoSunat($e->getMessage());
        }
    }

    /**
     * Verificar si está en modo simulación
     */
    public function esModoSimulacion(): bool
    {
        return $this->modoSimulacion;
    }

    /**
     * Obtener información de la empresa configurada
     */
    public function obtenerDatosEmpresa(): array
    {
        return [
            'ruc' => config('greenter.ruc'),
            'razon_social' => config('greenter.razon_social'),
            'nombre_comercial' => config('greenter.nombre_comercial'),
            'direccion' => config('greenter.direccion'),
            'ubigeo' => config('greenter.ubigeo'),
            'departamento' => config('greenter.departamento'),
            'provincia' => config('greenter.provincia'),
            'distrito' => config('greenter.distrito'),
        ];
    }
}
