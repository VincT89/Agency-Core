START TRANSACTION;

SET @saldo_utente = 7;
SET @saldo_riferimento = CONCAT('SALDO-SCADENZA-', UUID());

/** Il blocco impedisce pagamenti concorrenti sulle fatture durante il saldo. */
SELECT id
FROM invoices
WHERE status IN ('issued', 'partially_paid', 'overdue')
ORDER BY id
FOR UPDATE;

INSERT INTO payments (
    invoice_id, client_id, project_id, created_by,
    payment_date, amount, method, reference, notes, created_at, updated_at
)
SELECT
    i.id, i.client_id, i.project_id, u.id,
    i.due_date,
    COALESCE(i.payable_total, i.total) - i.credited_total - COALESCE(p.incassato, 0),
    'bank_transfer', @saldo_riferimento,
    'Saldo registrato in blocco: bonifico, data coincidente con la scadenza.',
    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM invoices AS i
JOIN users AS u ON u.id = @saldo_utente AND u.status = 'active'
    AND (u.role IN ('admin', 'administration') OR (u.role = 'developer' AND u.admin_access = 1))
LEFT JOIN (
    SELECT invoice_id, SUM(amount) AS incassato
    FROM payments
    GROUP BY invoice_id
) AS p ON p.invoice_id = i.id
WHERE i.status IN ('issued', 'partially_paid', 'overdue')
    AND i.due_date IS NOT NULL
    AND i.paid_total = COALESCE(p.incassato, 0)
    AND COALESCE(i.payable_total, i.total) - i.credited_total > COALESCE(p.incassato, 0);

SET @saldi_creati = ROW_COUNT();

UPDATE invoices AS i
JOIN payments AS saldo ON saldo.invoice_id = i.id AND CAST(saldo.reference AS BINARY) = CAST(@saldo_riferimento AS BINARY)
JOIN (
    SELECT invoice_id, SUM(amount) AS incassato
    FROM payments
    GROUP BY invoice_id
) AS p ON p.invoice_id = i.id
SET i.paid_total = p.incassato,
    i.status = 'paid',
    i.updated_at = CURRENT_TIMESTAMP
WHERE i.status IN ('issued', 'partially_paid', 'overdue');

SET @fatture_saldate = ROW_COUNT();

COMMIT;

SELECT @saldi_creati AS pagamenti_creati, @fatture_saldate AS fatture_saldate;

/** Le fatture rimaste aperte richiedono una scadenza o la verifica degli incassi preesistenti. */
SELECT i.id, i.number, i.due_date, i.paid_total, COALESCE(p.incassato, 0) AS pagamenti_registrati
FROM invoices AS i
LEFT JOIN (
    SELECT invoice_id, SUM(amount) AS incassato
    FROM payments
    GROUP BY invoice_id
) AS p ON p.invoice_id = i.id
WHERE i.status IN ('issued', 'partially_paid', 'overdue')
ORDER BY i.id;
