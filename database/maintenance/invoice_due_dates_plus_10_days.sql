/** Eseguire nel database di produzione del gestionale; la tabella di backup conserva le date precedenti. */
/** La tabella di backup deve essere nuova: non eliminarla o sovrascriverla se esiste già. */
CREATE TABLE invoice_due_dates_backup_20261021 AS
SELECT id, issue_date, due_date, updated_at FROM invoices WHERE 1 = 0;

START TRANSACTION;

INSERT INTO invoice_due_dates_backup_20261021 (id, issue_date, due_date, updated_at)
SELECT id, issue_date, due_date, updated_at FROM invoices;

/** La condizione sulle date evita di sovrascrivere modifiche intervenute dopo la copia. */
UPDATE invoices AS i
INNER JOIN invoice_due_dates_backup_20261021 AS b ON b.id = i.id
    AND i.issue_date <=> b.issue_date
    AND i.due_date <=> b.due_date
    AND i.updated_at <=> b.updated_at
SET i.due_date = DATE_ADD(b.issue_date, INTERVAL 10 DAY),
    i.updated_at = CURRENT_TIMESTAMP
WHERE b.issue_date IS NOT NULL
    AND NOT (i.due_date <=> DATE_ADD(b.issue_date, INTERVAL 10 DAY));

SELECT ROW_COUNT() AS scadenze_aggiornate;

COMMIT;

SELECT COUNT(*) AS fatture_totali,
    COALESCE(SUM(issue_date IS NULL), 0) AS fatture_senza_data_emissione,
    COALESCE(SUM(issue_date IS NOT NULL AND NOT (due_date <=> DATE_ADD(issue_date, INTERVAL 10 DAY))), 0) AS scadenze_da_verificare
FROM invoices;
