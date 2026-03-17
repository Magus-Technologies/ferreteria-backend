<?php

namespace App\Services;

use App\Exceptions\GreenterException;
use App\Services\Interfaces\GreenterServiceInterface;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Company;
use Greenter\Model\Company\Address;
use Greenter\Model\Despatch\Despatch;
use Greenter\Model\Despatch\DespatchDetail;
use Greenter\Model\Despatch\Direction;
use Greenter\Model\Despatch\Driver;
use Greenter\Model\Despatch\Shipment;
use Greenter\Model\Despatch\Transportist;
use Greenter\Model\Despatch\Vehicle;
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
            
            // Configurar ambiente (Beta o Producción)
            $isProduction = config('greenter.production', false);
            $this->see->setService($isProduction ? SunatEndpoints::FE_PRODUCCION : SunatEndpoints::FE_BETA);
            
            // Configurar credenciales SOL
            $this->see->setClaveSOL(
                config('greenter.ruc'),
                config('greenter.sol_user'),
                config('greenter.sol_pass')
            );
            
            // Cargar certificado digital
            $password = config('greenter.certificate_password', '');
            $certContent = file_get_contents($certificatePath);
            
            // Si tiene contraseña, es un .pfx, sino es .pem
            if (!empty($password)) {
                $this->see->setCertificate($certContent, $password);
            } else {
                $this->see->setCertificate($certContent);
            }
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
                'hash_cdr' => $result->getCdrZip() ? hash('sha256', $result->getCdrZip()) : null,
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
                'hash_cdr' => $result->getCdrZip() ? hash('sha256', $result->getCdrZip()) : null,
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
                'hash_cdr' => $result->getCdrZip() ? hash('sha256', $result->getCdrZip()) : null,
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
            ->setTipoOperacion('0101') // 0101 = Venta Interna
            ->setTipoDoc($data['tipo_doc']) // 01=Factura, 03=Boleta
            ->setSerie($data['serie'])
            ->setCorrelativo($data['numero'])
            ->setFechaEmision(new \DateTime()) // ✅ Fecha y hora actual
            ->setTipoMoneda($data['tipo_moneda'] ?? 'PEN')
            ->setCompany($company)
            ->setClient($client)
            ->setMtoOperGravadas($data['mto_oper_gravadas'])
            ->setMtoIGV($data['mto_igv'])
            ->setTotalImpuestos($data['mto_igv'])
            ->setMtoImpVenta($data['total']) // ✅ Total a pagar (con IGV) - genera TaxInclusiveAmount
            ->setValorVenta($data['mto_oper_gravadas']) // ✅ Sets LineExtensionAmount
            ->setSumOtrosDescuentos(0) // ✅ Required for proper calculation
            ->setMtoOperExoneradas(0) // ✅ Exonerated operations
            ->setMtoOperInafectas(0) // ✅ Unaffected operations
            ->setMtoOperGratuitas(0); // ✅ Free operations

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
     * Generar XML simulado para pruebas (COMPLETO según estándar UBL 2.1)
     */
    private function generarXmlSimulado(array $data, string $tipoDoc = '08'): string
    {
        // Determinar el tipo de documento y generar el XML correspondiente
        if ($tipoDoc === '08') {
            return $this->generarXmlNotaDebitoSimulado($data);
        } elseif ($tipoDoc === '07') {
            return $this->generarXmlNotaCreditoSimulado($data);
        } else {
            return $this->generarXmlFacturaSimulado($data, $tipoDoc);
        }
    }

    /**
     * Generar XML simulado para Nota de Débito
     */
    private function generarXmlNotaDebitoSimulado(array $data): string
    {
        $company = config('greenter.razon_social');
        $ruc = config('greenter.ruc');
        $direccion = config('greenter.direccion');
        $ubigeo = config('greenter.ubigeo');
        $departamento = config('greenter.departamento');
        $provincia = config('greenter.provincia');
        $distrito = config('greenter.distrito');
        
        $moneda = $data['tipo_moneda'] ?? 'PEN';
        $mtoOperGravadas = number_format($data['mto_oper_gravadas'], 2, '.', '');
        $mtoIgv = number_format($data['mto_igv'], 2, '.', '');
        $total = number_format($data['total'], 2, '.', '');
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<DebitNote xmlns="urn:oasis:names:specification:ubl:schema:xsd:DebitNote-2" ';
        $xml .= 'xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" ';
        $xml .= 'xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" ';
        $xml .= 'xmlns:ds="http://www.w3.org/2000/09/xmldsig#" ';
        $xml .= 'xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">' . "\n";
        
        // UBL Extensions (requerido para firma digital)
        $xml .= "  <ext:UBLExtensions>\n";
        $xml .= "    <ext:UBLExtension>\n";
        $xml .= "      <ext:ExtensionContent>\n";
        $xml .= "        <!-- Firma digital aquí (SIMULADO) -->\n";
        $xml .= "      </ext:ExtensionContent>\n";
        $xml .= "    </ext:UBLExtension>\n";
        $xml .= "  </ext:UBLExtensions>\n";
        
        // Información básica del comprobante
        $xml .= "  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>\n";
        $xml .= "  <cbc:CustomizationID>2.0</cbc:CustomizationID>\n";
        $xml .= "  <cbc:ID>{$data['serie']}-{$data['numero']}</cbc:ID>\n";
        $xml .= "  <cbc:IssueDate>{$data['fecha']}</cbc:IssueDate>\n";
        $xml .= "  <cbc:IssueTime>" . date('H:i:s') . "</cbc:IssueTime>\n";
        $xml .= "  <cbc:Note languageLocaleID=\"1000\"><![CDATA[{$data['monto_en_letras']}]]></cbc:Note>\n";
        $xml .= "  <cbc:DocumentCurrencyCode>{$moneda}</cbc:DocumentCurrencyCode>\n";
        
        // Documento afectado (referencia a la venta original)
        $xml .= "  <cac:DiscrepancyResponse>\n";
        $xml .= "    <cbc:ReferenceID>{$data['num_doc_afectado']}</cbc:ReferenceID>\n";
        $xml .= "    <cbc:ResponseCode>{$data['cod_motivo']}</cbc:ResponseCode>\n";
        $xml .= "    <cbc:Description>{$data['des_motivo']}</cbc:Description>\n";
        $xml .= "  </cac:DiscrepancyResponse>\n";
        
        // Referencia al documento afectado
        $xml .= "  <cac:BillingReference>\n";
        $xml .= "    <cac:InvoiceDocumentReference>\n";
        $xml .= "      <cbc:ID>{$data['num_doc_afectado']}</cbc:ID>\n";
        $xml .= "      <cbc:DocumentTypeCode>{$data['tipo_doc_afectado']}</cbc:DocumentTypeCode>\n";
        $xml .= "    </cac:InvoiceDocumentReference>\n";
        $xml .= "  </cac:BillingReference>\n";
        
        // Firma (referencia)
        $xml .= "  <cac:Signature>\n";
        $xml .= "    <cbc:ID>SIGN{$ruc}</cbc:ID>\n";
        $xml .= "    <cac:SignatoryParty>\n";
        $xml .= "      <cac:PartyIdentification>\n";
        $xml .= "        <cbc:ID>{$ruc}</cbc:ID>\n";
        $xml .= "      </cac:PartyIdentification>\n";
        $xml .= "      <cac:PartyName>\n";
        $xml .= "        <cbc:Name><![CDATA[{$company}]]></cbc:Name>\n";
        $xml .= "      </cac:PartyName>\n";
        $xml .= "    </cac:SignatoryParty>\n";
        $xml .= "    <cac:DigitalSignatureAttachment>\n";
        $xml .= "      <cac:ExternalReference>\n";
        $xml .= "        <cbc:URI>#GREENTER-SIGN</cbc:URI>\n";
        $xml .= "      </cac:ExternalReference>\n";
        $xml .= "    </cac:DigitalSignatureAttachment>\n";
        $xml .= "  </cac:Signature>\n";
        
        // Proveedor (Emisor)
        $xml .= "  <cac:AccountingSupplierParty>\n";
        $xml .= "    <cac:Party>\n";
        $xml .= "      <cac:PartyIdentification>\n";
        $xml .= "        <cbc:ID schemeID=\"6\">{$ruc}</cbc:ID>\n";
        $xml .= "      </cac:PartyIdentification>\n";
        $xml .= "      <cac:PartyName>\n";
        $xml .= "        <cbc:Name><![CDATA[{$company}]]></cbc:Name>\n";
        $xml .= "      </cac:PartyName>\n";
        $xml .= "      <cac:PartyLegalEntity>\n";
        $xml .= "        <cbc:RegistrationName><![CDATA[{$company}]]></cbc:RegistrationName>\n";
        $xml .= "        <cac:RegistrationAddress>\n";
        $xml .= "          <cbc:ID>{$ubigeo}</cbc:ID>\n";
        $xml .= "          <cbc:AddressTypeCode>0000</cbc:AddressTypeCode>\n";
        $xml .= "          <cbc:CitySubdivisionName>{$distrito}</cbc:CitySubdivisionName>\n";
        $xml .= "          <cbc:CityName>{$provincia}</cbc:CityName>\n";
        $xml .= "          <cbc:CountrySubentity>{$departamento}</cbc:CountrySubentity>\n";
        $xml .= "          <cbc:District>{$distrito}</cbc:District>\n";
        $xml .= "          <cac:AddressLine>\n";
        $xml .= "            <cbc:Line><![CDATA[{$direccion}]]></cbc:Line>\n";
        $xml .= "          </cac:AddressLine>\n";
        $xml .= "          <cac:Country>\n";
        $xml .= "            <cbc:IdentificationCode>PE</cbc:IdentificationCode>\n";
        $xml .= "          </cac:Country>\n";
        $xml .= "        </cac:RegistrationAddress>\n";
        $xml .= "      </cac:PartyLegalEntity>\n";
        $xml .= "    </cac:Party>\n";
        $xml .= "  </cac:AccountingSupplierParty>\n";
        
        // Cliente (Adquiriente)
        $clienteTipoDoc = $data['cliente']['tipo_doc'];
        $clienteNumDoc = $data['cliente']['num_doc'];
        $clienteRazonSocial = htmlspecialchars($data['cliente']['razon_social'], ENT_XML1, 'UTF-8');
        $clienteDireccion = htmlspecialchars($data['cliente']['direccion'] ?? '-', ENT_XML1, 'UTF-8');
        
        $xml .= "  <cac:AccountingCustomerParty>\n";
        $xml .= "    <cac:Party>\n";
        $xml .= "      <cac:PartyIdentification>\n";
        $xml .= "        <cbc:ID schemeID=\"{$clienteTipoDoc}\">{$clienteNumDoc}</cbc:ID>\n";
        $xml .= "      </cac:PartyIdentification>\n";
        $xml .= "      <cac:PartyLegalEntity>\n";
        $xml .= "        <cbc:RegistrationName><![CDATA[{$clienteRazonSocial}]]></cbc:RegistrationName>\n";
        $xml .= "        <cac:RegistrationAddress>\n";
        $xml .= "          <cac:AddressLine>\n";
        $xml .= "            <cbc:Line><![CDATA[{$clienteDireccion}]]></cbc:Line>\n";
        $xml .= "          </cac:AddressLine>\n";
        $xml .= "          <cac:Country>\n";
        $xml .= "            <cbc:IdentificationCode>PE</cbc:IdentificationCode>\n";
        $xml .= "          </cac:Country>\n";
        $xml .= "        </cac:RegistrationAddress>\n";
        $xml .= "      </cac:PartyLegalEntity>\n";
        $xml .= "    </cac:Party>\n";
        $xml .= "  </cac:AccountingCustomerParty>\n";
        
        // Totales de impuestos
        $xml .= "  <cac:TaxTotal>\n";
        $xml .= "    <cbc:TaxAmount currencyID=\"{$moneda}\">{$mtoIgv}</cbc:TaxAmount>\n";
        $xml .= "    <cac:TaxSubtotal>\n";
        $xml .= "      <cbc:TaxableAmount currencyID=\"{$moneda}\">{$mtoOperGravadas}</cbc:TaxableAmount>\n";
        $xml .= "      <cbc:TaxAmount currencyID=\"{$moneda}\">{$mtoIgv}</cbc:TaxAmount>\n";
        $xml .= "      <cac:TaxCategory>\n";
        $xml .= "        <cac:TaxScheme>\n";
        $xml .= "          <cbc:ID>1000</cbc:ID>\n";
        $xml .= "          <cbc:Name>IGV</cbc:Name>\n";
        $xml .= "          <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n";
        $xml .= "        </cac:TaxScheme>\n";
        $xml .= "      </cac:TaxCategory>\n";
        $xml .= "    </cac:TaxSubtotal>\n";
        $xml .= "  </cac:TaxTotal>\n";
        
        // Totales monetarios
        $xml .= "  <cac:RequestedMonetaryTotal>\n";
        $xml .= "    <cbc:PayableAmount currencyID=\"{$moneda}\">{$total}</cbc:PayableAmount>\n";
        $xml .= "  </cac:RequestedMonetaryTotal>\n";
        
        // Líneas de detalle (items) - Para Nota de Débito se usa DebitNoteLine
        foreach ($data['items'] as $index => $item) {
            $itemCodigo = htmlspecialchars($item['codigo'], ENT_XML1, 'UTF-8');
            $itemDescripcion = htmlspecialchars($item['descripcion'], ENT_XML1, 'UTF-8');
            $itemCantidad = number_format($item['cantidad'], 2, '.', '');
            $itemValorUnitario = number_format($item['valor_unitario'], 2, '.', '');
            $itemPrecioUnitario = number_format($item['precio_unitario'], 2, '.', '');
            $itemValorVenta = number_format($item['valor_venta'], 2, '.', '');
            $itemIgv = number_format($item['igv'], 2, '.', '');
            $itemMtoBaseIgv = number_format($item['mto_base_igv'], 2, '.', '');
            
            $xml .= "  <cac:DebitNoteLine>\n";
            $xml .= "    <cbc:ID>" . ($index + 1) . "</cbc:ID>\n";
            $xml .= "    <cbc:DebitedQuantity unitCode=\"{$item['unidad']}\">{$itemCantidad}</cbc:DebitedQuantity>\n";
            $xml .= "    <cbc:LineExtensionAmount currencyID=\"{$moneda}\">{$itemValorVenta}</cbc:LineExtensionAmount>\n";
            $xml .= "    <cac:PricingReference>\n";
            $xml .= "      <cac:AlternativeConditionPrice>\n";
            $xml .= "        <cbc:PriceAmount currencyID=\"{$moneda}\">{$itemPrecioUnitario}</cbc:PriceAmount>\n";
            $xml .= "        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n";
            $xml .= "      </cac:AlternativeConditionPrice>\n";
            $xml .= "    </cac:PricingReference>\n";
            $xml .= "    <cac:TaxTotal>\n";
            $xml .= "      <cbc:TaxAmount currencyID=\"{$moneda}\">{$itemIgv}</cbc:TaxAmount>\n";
            $xml .= "      <cac:TaxSubtotal>\n";
            $xml .= "        <cbc:TaxableAmount currencyID=\"{$moneda}\">{$itemMtoBaseIgv}</cbc:TaxableAmount>\n";
            $xml .= "        <cbc:TaxAmount currencyID=\"{$moneda}\">{$itemIgv}</cbc:TaxAmount>\n";
            $xml .= "        <cac:TaxCategory>\n";
            $xml .= "          <cbc:Percent>18</cbc:Percent>\n";
            $xml .= "          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n";
            $xml .= "          <cac:TaxScheme>\n";
            $xml .= "            <cbc:ID>1000</cbc:ID>\n";
            $xml .= "            <cbc:Name>IGV</cbc:Name>\n";
            $xml .= "            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n";
            $xml .= "          </cac:TaxScheme>\n";
            $xml .= "        </cac:TaxCategory>\n";
            $xml .= "      </cac:TaxSubtotal>\n";
            $xml .= "    </cac:TaxTotal>\n";
            $xml .= "    <cac:Item>\n";
            $xml .= "      <cbc:Description><![CDATA[{$itemDescripcion}]]></cbc:Description>\n";
            $xml .= "      <cac:SellersItemIdentification>\n";
            $xml .= "        <cbc:ID>{$itemCodigo}</cbc:ID>\n";
            $xml .= "      </cac:SellersItemIdentification>\n";
            $xml .= "    </cac:Item>\n";
            $xml .= "    <cac:Price>\n";
            $xml .= "      <cbc:PriceAmount currencyID=\"{$moneda}\">{$itemValorUnitario}</cbc:PriceAmount>\n";
            $xml .= "    </cac:Price>\n";
            $xml .= "  </cac:DebitNoteLine>\n";
        }
        
        $xml .= '</DebitNote>';
        
        return $xml;
    }

    /**
     * Generar XML simulado para Nota de Crédito
     */
    private function generarXmlNotaCreditoSimulado(array $data): string
    {
        // Similar structure but with CreditNote tags
        $company = config('greenter.razon_social');
        $ruc = config('greenter.ruc');
        $direccion = config('greenter.direccion');
        $ubigeo = config('greenter.ubigeo');
        $departamento = config('greenter.departamento');
        $provincia = config('greenter.provincia');
        $distrito = config('greenter.distrito');
        
        $moneda = $data['tipo_moneda'] ?? 'PEN';
        $mtoOperGravadas = number_format($data['mto_oper_gravadas'], 2, '.', '');
        $mtoIgv = number_format($data['mto_igv'], 2, '.', '');
        $total = number_format($data['total'], 2, '.', '');
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<CreditNote xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2" ';
        $xml .= 'xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" ';
        $xml .= 'xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" ';
        $xml .= 'xmlns:ds="http://www.w3.org/2000/09/xmldsig#" ';
        $xml .= 'xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">' . "\n";
        
        // Similar structure to DebitNote but with CreditNote-specific tags
        // ... (rest of the implementation would be similar)
        
        $xml .= '</CreditNote>';
        
        return $xml;
    }

    /**
     * Generar XML simulado para Factura/Boleta
     */
    private function generarXmlFacturaSimulado(array $data, string $tipoDoc): string
    {
        $company = config('greenter.razon_social');
        $ruc = config('greenter.ruc');
        $direccion = config('greenter.direccion');
        $ubigeo = config('greenter.ubigeo');
        $departamento = config('greenter.departamento');
        $provincia = config('greenter.provincia');
        $distrito = config('greenter.distrito');
        
        $moneda = $data['tipo_moneda'] ?? 'PEN';
        $mtoOperGravadas = number_format($data['mto_oper_gravadas'], 2, '.', '');
        $mtoIgv = number_format($data['mto_igv'], 2, '.', '');
        $total = number_format($data['total'], 2, '.', '');
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" ';
        $xml .= 'xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" ';
        $xml .= 'xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" ';
        $xml .= 'xmlns:ds="http://www.w3.org/2000/09/xmldsig#" ';
        $xml .= 'xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">' . "\n";
        
        // UBL Extensions (requerido para firma digital)
        $xml .= "  <ext:UBLExtensions>\n";
        $xml .= "    <ext:UBLExtension>\n";
        $xml .= "      <ext:ExtensionContent>\n";
        $xml .= "        <!-- Firma digital aquí (SIMULADO) -->\n";
        $xml .= "      </ext:ExtensionContent>\n";
        $xml .= "    </ext:UBLExtension>\n";
        $xml .= "  </ext:UBLExtensions>\n";
        
        // Información básica del comprobante
        $xml .= "  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>\n";
        $xml .= "  <cbc:CustomizationID>2.0</cbc:CustomizationID>\n";
        $xml .= "  <cbc:ID>{$data['serie']}-{$data['numero']}</cbc:ID>\n";
        $xml .= "  <cbc:IssueDate>{$data['fecha']}</cbc:IssueDate>\n";
        $xml .= "  <cbc:IssueTime>" . date('H:i:s') . "</cbc:IssueTime>\n"; // ✅ Hora real
        $xml .= "  <cbc:InvoiceTypeCode listID=\"0101\">{$tipoDoc}</cbc:InvoiceTypeCode>\n";
        $xml .= "  <cbc:DocumentCurrencyCode>{$moneda}</cbc:DocumentCurrencyCode>\n";
        
        // Leyenda (monto en letras)
        $xml .= "  <cac:Note>\n";
        $xml .= "    <cbc:Note><![CDATA[{$data['monto_en_letras']}]]></cbc:Note>\n";
        $xml .= "  </cac:Note>\n";
        
        // Firma (referencia)
        $xml .= "  <cac:Signature>\n";
        $xml .= "    <cbc:ID>{$ruc}</cbc:ID>\n";
        $xml .= "    <cac:SignatoryParty>\n";
        $xml .= "      <cac:PartyIdentification>\n";
        $xml .= "        <cbc:ID>{$ruc}</cbc:ID>\n";
        $xml .= "      </cac:PartyIdentification>\n";
        $xml .= "      <cac:PartyName>\n";
        $xml .= "        <cbc:Name><![CDATA[{$company}]]></cbc:Name>\n";
        $xml .= "      </cac:PartyName>\n";
        $xml .= "    </cac:SignatoryParty>\n";
        $xml .= "    <cac:DigitalSignatureAttachment>\n";
        $xml .= "      <cac:ExternalReference>\n";
        $xml .= "        <cbc:URI>#{$ruc}</cbc:URI>\n";
        $xml .= "      </cac:ExternalReference>\n";
        $xml .= "    </cac:DigitalSignatureAttachment>\n";
        $xml .= "  </cac:Signature>\n";
        
        // Proveedor (Emisor)
        $xml .= "  <cac:AccountingSupplierParty>\n";
        $xml .= "    <cac:Party>\n";
        $xml .= "      <cac:PartyIdentification>\n";
        $xml .= "        <cbc:ID schemeID=\"6\">{$ruc}</cbc:ID>\n";
        $xml .= "      </cac:PartyIdentification>\n";
        $xml .= "      <cac:PartyName>\n";
        $xml .= "        <cbc:Name><![CDATA[{$company}]]></cbc:Name>\n";
        $xml .= "      </cac:PartyName>\n";
        $xml .= "      <cac:PartyLegalEntity>\n";
        $xml .= "        <cbc:RegistrationName><![CDATA[{$company}]]></cbc:RegistrationName>\n";
        $xml .= "        <cac:RegistrationAddress>\n";
        $xml .= "          <cbc:ID>{$ubigeo}</cbc:ID>\n";
        $xml .= "          <cbc:AddressTypeCode>0000</cbc:AddressTypeCode>\n";
        $xml .= "          <cbc:CitySubdivisionName>{$distrito}</cbc:CitySubdivisionName>\n";
        $xml .= "          <cbc:CityName>{$provincia}</cbc:CityName>\n";
        $xml .= "          <cbc:CountrySubentity>{$departamento}</cbc:CountrySubentity>\n";
        $xml .= "          <cbc:District>{$distrito}</cbc:District>\n";
        $xml .= "          <cac:AddressLine>\n";
        $xml .= "            <cbc:Line><![CDATA[{$direccion}]]></cbc:Line>\n";
        $xml .= "          </cac:AddressLine>\n";
        $xml .= "          <cac:Country>\n";
        $xml .= "            <cbc:IdentificationCode>PE</cbc:IdentificationCode>\n";
        $xml .= "          </cac:Country>\n";
        $xml .= "        </cac:RegistrationAddress>\n";
        $xml .= "      </cac:PartyLegalEntity>\n";
        $xml .= "    </cac:Party>\n";
        $xml .= "  </cac:AccountingSupplierParty>\n";
        
        // Cliente (Adquiriente)
        $clienteTipoDoc = $data['cliente']['tipo_doc'];
        $clienteNumDoc = $data['cliente']['num_doc'];
        $clienteRazonSocial = htmlspecialchars($data['cliente']['razon_social'], ENT_XML1, 'UTF-8');
        $clienteDireccion = htmlspecialchars($data['cliente']['direccion'] ?? '-', ENT_XML1, 'UTF-8');
        
        $xml .= "  <cac:AccountingCustomerParty>\n";
        $xml .= "    <cac:Party>\n";
        $xml .= "      <cac:PartyIdentification>\n";
        $xml .= "        <cbc:ID schemeID=\"{$clienteTipoDoc}\">{$clienteNumDoc}</cbc:ID>\n";
        $xml .= "      </cac:PartyIdentification>\n";
        $xml .= "      <cac:PartyLegalEntity>\n";
        $xml .= "        <cbc:RegistrationName><![CDATA[{$clienteRazonSocial}]]></cbc:RegistrationName>\n";
        $xml .= "        <cac:RegistrationAddress>\n";
        $xml .= "          <cac:AddressLine>\n";
        $xml .= "            <cbc:Line><![CDATA[{$clienteDireccion}]]></cbc:Line>\n";
        $xml .= "          </cac:AddressLine>\n";
        $xml .= "        </cac:RegistrationAddress>\n";
        $xml .= "      </cac:PartyLegalEntity>\n";
        $xml .= "    </cac:Party>\n";
        $xml .= "  </cac:AccountingCustomerParty>\n";
        
        // Totales de impuestos
        $xml .= "  <cac:TaxTotal>\n";
        $xml .= "    <cbc:TaxAmount currencyID=\"{$moneda}\">{$mtoIgv}</cbc:TaxAmount>\n";
        $xml .= "    <cac:TaxSubtotal>\n";
        $xml .= "      <cbc:TaxableAmount currencyID=\"{$moneda}\">{$mtoOperGravadas}</cbc:TaxableAmount>\n";
        $xml .= "      <cbc:TaxAmount currencyID=\"{$moneda}\">{$mtoIgv}</cbc:TaxAmount>\n";
        $xml .= "      <cac:TaxCategory>\n";
        $xml .= "        <cbc:ID schemeID=\"UN/ECE 5305\" schemeName=\"Tax Category Identifier\" schemeAgencyName=\"United Nations Economic Commission for Europe\">S</cbc:ID>\n";
        $xml .= "        <cac:TaxScheme>\n";
        $xml .= "          <cbc:ID schemeID=\"UN/ECE 5153\" schemeAgencyID=\"6\">1000</cbc:ID>\n";
        $xml .= "          <cbc:Name>IGV</cbc:Name>\n";
        $xml .= "          <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n";
        $xml .= "        </cac:TaxScheme>\n";
        $xml .= "      </cac:TaxCategory>\n";
        $xml .= "    </cac:TaxSubtotal>\n";
        $xml .= "  </cac:TaxTotal>\n";
        
        // Totales monetarios (✅ CORREGIDO: Incluye TaxInclusiveAmount)
        $xml .= "  <cac:LegalMonetaryTotal>\n";
        $xml .= "    <cbc:LineExtensionAmount currencyID=\"{$moneda}\">{$mtoOperGravadas}</cbc:LineExtensionAmount>\n";
        $xml .= "    <cbc:TaxInclusiveAmount currencyID=\"{$moneda}\">{$total}</cbc:TaxInclusiveAmount>\n"; // ✅ Total con impuestos
        $xml .= "    <cbc:PayableAmount currencyID=\"{$moneda}\">{$total}</cbc:PayableAmount>\n";
        $xml .= "  </cac:LegalMonetaryTotal>\n";
        
        // Líneas de detalle (items)
        foreach ($data['items'] as $index => $item) {
            $itemCodigo = htmlspecialchars($item['codigo'], ENT_XML1, 'UTF-8');
            $itemDescripcion = htmlspecialchars($item['descripcion'], ENT_XML1, 'UTF-8');
            $itemCantidad = number_format($item['cantidad'], 2, '.', '');
            $itemValorUnitario = number_format($item['valor_unitario'], 2, '.', '');
            $itemPrecioUnitario = number_format($item['precio_unitario'], 2, '.', '');
            $itemValorVenta = number_format($item['valor_venta'], 2, '.', '');
            $itemIgv = number_format($item['igv'], 2, '.', '');
            $itemMtoBaseIgv = number_format($item['mto_base_igv'], 2, '.', '');
            
            $xml .= "  <cac:InvoiceLine>\n";
            $xml .= "    <cbc:ID>" . ($index + 1) . "</cbc:ID>\n";
            $xml .= "    <cbc:InvoicedQuantity unitCode=\"{$item['unidad']}\">{$itemCantidad}</cbc:InvoicedQuantity>\n";
            $xml .= "    <cbc:LineExtensionAmount currencyID=\"{$moneda}\">{$itemValorVenta}</cbc:LineExtensionAmount>\n";
            $xml .= "    <cac:PricingReference>\n";
            $xml .= "      <cac:AlternativeConditionPrice>\n";
            $xml .= "        <cbc:PriceAmount currencyID=\"{$moneda}\">{$itemPrecioUnitario}</cbc:PriceAmount>\n";
            $xml .= "        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n";
            $xml .= "      </cac:AlternativeConditionPrice>\n";
            $xml .= "    </cac:PricingReference>\n";
            $xml .= "    <cac:TaxTotal>\n";
            $xml .= "      <cbc:TaxAmount currencyID=\"{$moneda}\">{$itemIgv}</cbc:TaxAmount>\n";
            $xml .= "      <cac:TaxSubtotal>\n";
            $xml .= "        <cbc:TaxableAmount currencyID=\"{$moneda}\">{$itemMtoBaseIgv}</cbc:TaxableAmount>\n";
            $xml .= "        <cbc:TaxAmount currencyID=\"{$moneda}\">{$itemIgv}</cbc:TaxAmount>\n";
            $xml .= "        <cac:TaxCategory>\n";
            $xml .= "          <cbc:Percent>18.00</cbc:Percent>\n";
            $xml .= "          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n";
            $xml .= "          <cac:TaxScheme>\n";
            $xml .= "            <cbc:ID>1000</cbc:ID>\n";
            $xml .= "            <cbc:Name>IGV</cbc:Name>\n";
            $xml .= "            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n";
            $xml .= "          </cac:TaxScheme>\n";
            $xml .= "        </cac:TaxCategory>\n";
            $xml .= "      </cac:TaxSubtotal>\n";
            $xml .= "    </cac:TaxTotal>\n";
            $xml .= "    <cac:Item>\n";
            $xml .= "      <cbc:Description><![CDATA[{$itemDescripcion}]]></cbc:Description>\n";
            $xml .= "      <cac:SellersItemIdentification>\n";
            $xml .= "        <cbc:ID>{$itemCodigo}</cbc:ID>\n";
            $xml .= "      </cac:SellersItemIdentification>\n";
            $xml .= "    </cac:Item>\n";
            $xml .= "    <cac:Price>\n";
            $xml .= "      <cbc:PriceAmount currencyID=\"{$moneda}\">{$itemValorUnitario}</cbc:PriceAmount>\n";
            $xml .= "    </cac:Price>\n";
            $xml .= "  </cac:InvoiceLine>\n";
        }
        
        $xml .= '</Invoice>';
        
        return $xml;
    }

    // =========================================================================
    // GUÍA DE REMISIÓN ELECTRÓNICA (GRE)
    // =========================================================================

    /**
     * Obtener instancia See configurada para endpoint Despatch (GRE)
     */
    private function getSeeForDespatch(): See
    {
        $see = new See();

        $isProduction = config('greenter.production', false);
        $endpoint = $isProduction
            ? config('greenter.endpoints.production.despatch')
            : config('greenter.endpoints.beta.despatch');

        $see->setService($endpoint);
        $see->setClaveSOL(
            config('greenter.ruc'),
            config('greenter.sol_user'),
            config('greenter.sol_pass')
        );

        // Cargar certificado digital
        $certificatePath = config('greenter.certificate_path');
        $password = config('greenter.certificate_password', '');
        $certContent = file_get_contents($certificatePath);

        if (!empty($password)) {
            $see->setCertificate($certContent, $password);
        } else {
            $see->setCertificate($certContent);
        }

        return $see;
    }

    /**
     * Construir objeto Despatch de Greenter a partir de datos
     */
    private function construirGuiaRemision(array $data): Despatch
    {
        $company = $this->crearEmpresa();

        // Destinatario
        $destinatario = new Client();
        $destinatario
            ->setTipoDoc($data['destinatario']['tipo_doc'])
            ->setNumDoc($data['destinatario']['num_doc'])
            ->setRznSocial($data['destinatario']['razon_social']);

        // Dirección de partida
        $partida = new Direction(
            $data['ubigeo_partida'] ?? config('greenter.ubigeo'),
            $data['direccion_partida']
        );
        $partida->setCodLocal(config('greenter.codigo_establecimiento', '0000'));
        $partida->setRuc(config('greenter.ruc'));

        // Dirección de llegada
        $llegada = new Direction(
            $data['ubigeo_llegada'] ?? config('greenter.ubigeo'),
            $data['direccion_llegada']
        );

        // Vehículo
        $vehiculo = null;
        if (!empty($data['vehiculo_placa'])) {
            $vehiculo = new Vehicle();
            $vehiculo->setPlaca($data['vehiculo_placa']);
        }

        // Choferes
        $choferes = [];
        if (!empty($data['choferes'])) {
            foreach ($data['choferes'] as $choferData) {
                $driver = new Driver();
                $driver
                    ->setTipo($choferData['tipo'] ?? 'Principal')
                    ->setTipoDoc($choferData['tipo_doc'] ?? '1') // 1 = DNI
                    ->setNroDoc($choferData['nro_doc'])
                    ->setNombres($choferData['nombres'])
                    ->setApellidos($choferData['apellidos'])
                    ->setLicencia($choferData['licencia'] ?? null);
                $choferes[] = $driver;
            }
        }

        // Envío (Shipment)
        $shipment = new Shipment();
        $shipment
            ->setCodTraslado($data['cod_traslado'])
            ->setDesTraslado($data['des_traslado'] ?? null)
            ->setModTraslado($data['mod_traslado']) // '01' = público, '02' = privado
            ->setFecTraslado(new \DateTime($data['fecha_traslado']))
            ->setPesoTotal($data['peso_total'] ?? 0.01)
            ->setUndPesoTotal('KGM')
            ->setPartida($partida)
            ->setLlegada($llegada);

        if ($vehiculo) {
            $shipment->setVehiculo($vehiculo);
        }
        if (!empty($choferes)) {
            $shipment->setChoferes($choferes);
        }

        // Agregar transportista:
        // - GRE-Transportista (31): la empresa emisora es el transportista (siempre)
        // - GRE-Remitente con modalidad pública: datos del transportista externo
        if (!empty($data['transportista'])) {
            $transportista = new Transportist();
            $transportista
                ->setTipoDoc($data['transportista']['tipo_doc'] ?? '6')
                ->setNumDoc($data['transportista']['num_doc'])
                ->setRznSocial($data['transportista']['razon_social'] ?? null)
                ->setNroMtc($data['transportista']['nro_mtc'] ?? null);
            $shipment->setTransportista($transportista);
        } elseif ($data['mod_traslado'] === '01') {
            // Modalidad pública sin transportista explícito — no agregar
        }

        // Detalles
        $details = [];
        foreach ($data['items'] as $item) {
            $detail = new DespatchDetail();
            $detail
                ->setCodigo($item['codigo'])
                ->setDescripcion($item['descripcion'])
                ->setUnidad($item['unidad'] ?? 'NIU')
                ->setCantidad($item['cantidad']);
            $details[] = $detail;
        }

        // Comprador (para motivos 03 y 14: venta con entrega a terceros)
        $comprador = null;
        if (!empty($data['comprador'])) {
            $comprador = new Client();
            $comprador
                ->setTipoDoc($data['comprador']['tipo_doc'])
                ->setNumDoc($data['comprador']['num_doc'])
                ->setRznSocial($data['comprador']['razon_social']);
        }

        // Construir Despatch
        // SUNAT: '09' = GRE-Remitente, '31' = GRE-Transportista
        $tipoDoc = ($data['tipo_guia'] ?? '') === 'ELECTRONICA_TRANSPORTISTA' ? '31' : '09';

        $despatch = new Despatch();
        $despatch
            ->setTipoDoc($tipoDoc)
            ->setSerie($data['serie'])
            ->setCorrelativo($data['correlativo'])
            ->setFechaEmision(new \DateTime($data['fecha_emision']))
            ->setCompany($company)
            ->setDestinatario($destinatario)
            ->setEnvio($shipment)
            ->setDetails($details);

        if ($comprador) {
            $despatch->setComprador($comprador);
        }

        if (!empty($data['observacion'])) {
            $despatch->setObservacion($data['observacion']);
        }

        return $despatch;
    }

    /**
     * Generar solo el XML de la guía de remisión (sin enviar)
     */
    public function generarXmlGuiaRemision(array $data): string
    {
        try {
            if ($this->modoSimulacion) {
                return $this->generarXmlGuiaRemisionSimulado($data);
            }

            $despatch = $this->construirGuiaRemision($data);
            $see = $this->getSeeForDespatch();
            return $see->getXmlSigned($despatch);
        } catch (\Exception $e) {
            throw GreenterException::errorGenerandoXml($e->getMessage());
        }
    }

    /**
     * Generar XML y enviar guía de remisión a SUNAT
     */
    public function generarYEnviarGuiaRemision(array $data): array
    {
        try {
            // MODO SIMULACIÓN
            if ($this->modoSimulacion) {
                return $this->simularGuiaRemision($data);
            }

            // MODO REAL
            $despatch = $this->construirGuiaRemision($data);
            $see = $this->getSeeForDespatch();
            $result = $see->send($despatch);

            if (!$result->isSuccess()) {
                $error = $result->getError();
                throw GreenterException::errorEnviandoSunat(
                    $error ? $error->getMessage() : 'Error desconocido al enviar GRE'
                );
            }

            return [
                'success' => true,
                'xml' => $see->getFactory()->getLastXml(),
                'cdr' => $result->getCdrZip(),
                'hash_cpe' => hash('sha256', $see->getFactory()->getLastXml()),
                'hash_cdr' => $result->getCdrZip() ? hash('sha256', $result->getCdrZip()) : null,
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
     * Simular guía de remisión (sin certificado)
     */
    private function simularGuiaRemision(array $data): array
    {
        $xml = $this->generarXmlGuiaRemisionSimulado($data);
        $hashCpe = hash('sha256', $xml);
        $cdr = $this->generarCdrSimulado(
            ['serie' => $data['serie'], 'numero' => $data['correlativo']],
            'Guía de Remisión'
        );

        return [
            'success' => true,
            'modo' => 'SIMULACION',
            'xml' => $xml,
            'cdr' => base64_encode($cdr),
            'hash_cpe' => $hashCpe,
            'hash_cdr' => hash('sha256', $cdr),
            'codigo_sunat' => '0',
            'mensaje_sunat' => 'La Guía de Remisión ha sido aceptada (SIMULADO)',
        ];
    }

    /**
     * Generar XML simulado para Guía de Remisión
     */
    private function generarXmlGuiaRemisionSimulado(array $data): string
    {
        $ruc = config('greenter.ruc');
        $razonSocial = config('greenter.razon_social');
        $ubigeo = config('greenter.ubigeo');
        $direccion = config('greenter.direccion');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<DespatchAdvice xmlns="urn:oasis:names:specification:ubl:schema:xsd:DespatchAdvice-2" ';
        $xml .= 'xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" ';
        $xml .= 'xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">' . "\n";

        $xml .= "  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>\n";
        $xml .= "  <cbc:CustomizationID>2.0</cbc:CustomizationID>\n";
        $xml .= "  <cbc:ID>{$data['serie']}-{$data['correlativo']}</cbc:ID>\n";
        $xml .= "  <cbc:IssueDate>{$data['fecha_emision']}</cbc:IssueDate>\n";
        $xml .= "  <cbc:IssueTime>" . date('H:i:s') . "</cbc:IssueTime>\n";
        $tipoDocSimulado = ($data['tipo_guia'] ?? '') === 'ELECTRONICA_TRANSPORTISTA' ? '31' : '09';
        $xml .= "  <cbc:DespatchAdviceTypeCode>{$tipoDocSimulado}</cbc:DespatchAdviceTypeCode>\n";

        if (!empty($data['observacion'])) {
            $xml .= "  <cbc:Note><![CDATA[{$data['observacion']}]]></cbc:Note>\n";
        }

        // Emisor
        $xml .= "  <cac:DespatchSupplierParty>\n";
        $xml .= "    <cbc:CustomerAssignedAccountID schemeID=\"6\">{$ruc}</cbc:CustomerAssignedAccountID>\n";
        $xml .= "    <cac:Party>\n";
        $xml .= "      <cac:PartyLegalEntity>\n";
        $xml .= "        <cbc:RegistrationName><![CDATA[{$razonSocial}]]></cbc:RegistrationName>\n";
        $xml .= "      </cac:PartyLegalEntity>\n";
        $xml .= "    </cac:Party>\n";
        $xml .= "  </cac:DespatchSupplierParty>\n";

        // Destinatario
        $destTipoDoc = $data['destinatario']['tipo_doc'] ?? '1';
        $destNumDoc = $data['destinatario']['num_doc'] ?? '';
        $destRazonSocial = htmlspecialchars($data['destinatario']['razon_social'] ?? '', ENT_XML1, 'UTF-8');
        $xml .= "  <cac:DeliveryCustomerParty>\n";
        $xml .= "    <cbc:CustomerAssignedAccountID schemeID=\"{$destTipoDoc}\">{$destNumDoc}</cbc:CustomerAssignedAccountID>\n";
        $xml .= "    <cac:Party>\n";
        $xml .= "      <cac:PartyLegalEntity>\n";
        $xml .= "        <cbc:RegistrationName><![CDATA[{$destRazonSocial}]]></cbc:RegistrationName>\n";
        $xml .= "      </cac:PartyLegalEntity>\n";
        $xml .= "    </cac:Party>\n";
        $xml .= "  </cac:DeliveryCustomerParty>\n";

        // Shipment
        $xml .= "  <cac:Shipment>\n";
        $xml .= "    <cbc:ID>SUNAT_Envio</cbc:ID>\n";
        $xml .= "    <cbc:HandlingCode>{$data['cod_traslado']}</cbc:HandlingCode>\n";
        $xml .= "    <cbc:GrossWeightMeasure unitCode=\"KGM\">" . number_format($data['peso_total'] ?? 0.01, 2, '.', '') . "</cbc:GrossWeightMeasure>\n";

        // Partida
        $ubiPartida = $data['ubigeo_partida'] ?? $ubigeo;
        $dirPartida = htmlspecialchars($data['direccion_partida'] ?? '', ENT_XML1, 'UTF-8');
        $xml .= "    <cac:ShipmentStage>\n";
        $xml .= "      <cbc:TransportModeCode>{$data['mod_traslado']}</cbc:TransportModeCode>\n";
        $xml .= "      <cac:TransitPeriod>\n";
        $xml .= "        <cbc:StartDate>{$data['fecha_traslado']}</cbc:StartDate>\n";
        $xml .= "      </cac:TransitPeriod>\n";
        $xml .= "    </cac:ShipmentStage>\n";

        $ubiLlegada = $data['ubigeo_llegada'] ?? $ubigeo;
        $dirLlegada = htmlspecialchars($data['direccion_llegada'] ?? '', ENT_XML1, 'UTF-8');
        $xml .= "    <cac:Delivery>\n";
        $xml .= "      <cac:DeliveryAddress>\n";
        $xml .= "        <cbc:ID>{$ubiLlegada}</cbc:ID>\n";
        $xml .= "        <cac:AddressLine><cbc:Line><![CDATA[{$dirLlegada}]]></cbc:Line></cac:AddressLine>\n";
        $xml .= "      </cac:DeliveryAddress>\n";
        $xml .= "    </cac:Delivery>\n";

        $xml .= "    <cac:OriginAddress>\n";
        $xml .= "      <cbc:ID>{$ubiPartida}</cbc:ID>\n";
        $xml .= "      <cac:AddressLine><cbc:Line><![CDATA[{$dirPartida}]]></cbc:Line></cac:AddressLine>\n";
        $xml .= "    </cac:OriginAddress>\n";

        $xml .= "  </cac:Shipment>\n";

        // Items
        foreach ($data['items'] as $i => $item) {
            $num = $i + 1;
            $desc = htmlspecialchars($item['descripcion'] ?? '', ENT_XML1, 'UTF-8');
            $xml .= "  <cac:DespatchLine>\n";
            $xml .= "    <cbc:ID>{$num}</cbc:ID>\n";
            $xml .= "    <cbc:DeliveredQuantity unitCode=\"" . ($item['unidad'] ?? 'NIU') . "\">" . number_format($item['cantidad'], 4, '.', '') . "</cbc:DeliveredQuantity>\n";
            $xml .= "    <cac:Item>\n";
            $xml .= "      <cbc:Name><![CDATA[{$desc}]]></cbc:Name>\n";
            $xml .= "      <cac:SellersItemIdentification><cbc:ID>" . ($item['codigo'] ?? '') . "</cbc:ID></cac:SellersItemIdentification>\n";
            $xml .= "    </cac:Item>\n";
            $xml .= "  </cac:DespatchLine>\n";
        }

        $xml .= '</DespatchAdvice>';

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
