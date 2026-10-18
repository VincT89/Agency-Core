<?php

namespace Tests\Fixtures;

class InvoiceImportXml
{
    public static function make(string $number = 'FPR 1/26', string $type = 'TD01', bool $split = false, string $description = 'Servizio dimostrativo'): string
    {
        $format = $split ? 'FPA12' : 'FPR12';
        $payment = $split ? '100.00' : '122.00';
        $exigibility = $split ? '<EsigibilitaIVA>S</EsigibilitaIVA>' : '';
        $number = htmlspecialchars($number, ENT_XML1);
        $description = htmlspecialchars($description, ENT_XML1);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<p:FatturaElettronica xmlns:p="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2" versione="{$format}">
<FatturaElettronicaHeader><DatiTrasmissione><ProgressivoInvio>1</ProgressivoInvio><FormatoTrasmissione>{$format}</FormatoTrasmissione></DatiTrasmissione>
<CedentePrestatore><DatiAnagrafici><IdFiscaleIVA><IdPaese>IT</IdPaese><IdCodice>01234567897</IdCodice></IdFiscaleIVA><Anagrafica><Denominazione>Agenzia dimostrativa</Denominazione></Anagrafica><RegimeFiscale>RF01</RegimeFiscale></DatiAnagrafici></CedentePrestatore>
<CessionarioCommittente><DatiAnagrafici><IdFiscaleIVA><IdPaese>IT</IdPaese><IdCodice>01879020517</IdCodice></IdFiscaleIVA><Anagrafica><Denominazione>Cliente dimostrativo</Denominazione></Anagrafica></DatiAnagrafici><Sede><Indirizzo>Via di prova 1</Indirizzo><CAP>80100</CAP><Comune>Napoli</Comune><Provincia>NA</Provincia><Nazione>IT</Nazione></Sede></CessionarioCommittente></FatturaElettronicaHeader>
<FatturaElettronicaBody><DatiGenerali><DatiGeneraliDocumento><TipoDocumento>{$type}</TipoDocumento><Divisa>EUR</Divisa><Data>2026-10-15</Data><Numero>{$number}</Numero><ImportoTotaleDocumento>122.00</ImportoTotaleDocumento></DatiGeneraliDocumento></DatiGenerali>
<DatiBeniServizi><DettaglioLinee><NumeroLinea>1</NumeroLinea><Descrizione>{$description}</Descrizione><Quantita>1</Quantita><PrezzoUnitario>100.00</PrezzoUnitario><PrezzoTotale>100.00</PrezzoTotale><AliquotaIVA>22.00</AliquotaIVA></DettaglioLinee><DatiRiepilogo><AliquotaIVA>22.00</AliquotaIVA><ImponibileImporto>100.00</ImponibileImporto><Imposta>22.00</Imposta>{$exigibility}</DatiRiepilogo></DatiBeniServizi>
<DatiPagamento><CondizioniPagamento>TP02</CondizioniPagamento><DettaglioPagamento><DataScadenzaPagamento>2026-11-15</DataScadenzaPagamento><ImportoPagamento>{$payment}</ImportoPagamento></DettaglioPagamento></DatiPagamento></FatturaElettronicaBody></p:FatturaElettronica>
XML;
    }
}
