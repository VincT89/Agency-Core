<?php

namespace Database\Seeders;

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPeriod;
use App\Models\MarketingCampaignPost;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        // ── 1. Create Users ──────────────────────────────────────────────────
        $admin = User::firstOrCreate(['email' => 'admin@sodanoconsulting.it'], [
            'name' => 'Admin Sodano',
            'password' => $password,
            'role' => UserRole::Admin,
            'status' => 'active',
            'primary_specialization' => 'Amministrazione Sistema',
            'phone' => '+39 080 000 0001',
            'email_verified_at' => now(),
            'password_changed_at' => now(),
        ]);

        $administration = User::firstOrCreate(['email' => 'administration@sodanoconsulting.it'], [
            'name' => 'Ufficio Amministrazione',
            'password' => $password,
            'role' => UserRole::Administration,
            'status' => 'active',
            'primary_specialization' => 'Finance & Fatturazione',
            'phone' => '+39 080 000 0002',
            'email_verified_at' => now(),
            'password_changed_at' => now(),
        ]);

        $developer = User::firstOrCreate(['email' => 'developer@sodanoconsulting.it'], [
            'name' => 'Reparto Development',
            'password' => $password,
            'role' => UserRole::Developer,
            'status' => 'active',
            'primary_specialization' => 'Sviluppo Web e App',
            'phone' => '+39 080 000 0003',
            'email_verified_at' => now(),
            'password_changed_at' => now(),
        ]);

        $marketing = User::firstOrCreate(['email' => 'marketing@sodanoconsulting.it'], [
            'name' => 'Reparto Marketing',
            'password' => $password,
            'role' => UserRole::Marketing,
            'status' => 'active',
            'primary_specialization' => 'Growth & Social',
            'phone' => '+39 080 000 0004',
            'email_verified_at' => now(),
            'password_changed_at' => now(),
        ]);

        $photographer = User::firstOrCreate(['email' => 'photographer@sodanoconsulting.it'], [
            'name' => 'Reparto Fotografia',
            'password' => $password,
            'role' => UserRole::Photographer,
            'status' => 'active',
            'primary_specialization' => 'Shooting & Editing',
            'phone' => '+39 080 000 0005',
            'email_verified_at' => now(),
            'password_changed_at' => now(),
        ]);

        $graphicDesigner = User::firstOrCreate(['email' => 'graphic@sodanoconsulting.it'], [
            'name' => 'Reparto Grafica',
            'password' => $password,
            'role' => UserRole::GraphicDesigner,
            'status' => 'active',
            'primary_specialization' => 'UI/UX & Branding',
            'phone' => '+39 080 000 0006',
            'email_verified_at' => now(),
            'password_changed_at' => now(),
        ]);

        // ── 2. Create Clients ────────────────────────────────────────────────
        $alphaClient = Client::firstOrCreate(['slug' => 'alpha-srl'], [
            'name' => 'Alpha Srl',
            'company_name' => 'Alpha Società a Responsabilità Limitata',
            'email' => 'info@alpha.it',
            'phone' => '+39 02 123456',
            'reference_person' => 'Mario Rossi',
            'vat_number' => '12345678901',
            'status' => 'active',
            'notes' => 'Cliente storico, segmento tech/retail.',
        ]);

        $betaClient = Client::firstOrCreate(['slug' => 'beta-studio'], [
            'name' => 'Beta Studio',
            'company_name' => 'Beta Creative Studio',
            'email' => 'hello@betastudio.com',
            'phone' => '+39 06 987654',
            'reference_person' => 'Laura Bianchi',
            'vat_number' => '09876543210',
            'status' => 'active',
            'notes' => 'Partnership su campagne creative esterne.',
        ]);

        $gammaClient = Client::firstOrCreate(['slug' => 'gamma-retail'], [
            'name' => 'Gamma Retail',
            'company_name' => 'Gamma SpA',
            'email' => 'retail@gamma.it',
            'phone' => '+39 011 555444',
            'reference_person' => 'Giorgio Verdi',
            'vat_number' => '11223344556',
            'status' => 'active',
            'notes' => 'Settore fashion. Progetti ad alto uso di asset.',
        ]);

        // ── 3. Create Teams ──────────────────────────────────────────────────
        $teamDigital = Team::firstOrCreate(['name' => 'Produzione Digitale'], [
            'description' => 'Team focalizzato su sviluppo e campagne digitali',
            'is_active' => true,
        ]);
        $teamDigital->users()->syncWithoutDetaching([
            $developer->id => ['role' => 'lead', 'assignment_status' => 'active', 'joined_at' => now()],
            $marketing->id => ['role' => 'member', 'assignment_status' => 'active', 'joined_at' => now()],
        ]);

        $teamCreative = Team::firstOrCreate(['name' => 'Creative Studio'], [
            'description' => 'Reparto per shooting, cataloghi e asset visuali',
            'is_active' => true,
        ]);
        $teamCreative->users()->syncWithoutDetaching([
            $photographer->id => ['role' => 'member', 'assignment_status' => 'active', 'joined_at' => now()],
            $graphicDesigner->id => ['role' => 'lead', 'assignment_status' => 'active', 'joined_at' => now()],
        ]);

        $teamAdmin = Team::firstOrCreate(['name' => 'Amministrazione'], [
            'description' => 'Gestione finanza, reporting e risorse HR',
            'is_active' => true,
        ]);
        $teamAdmin->users()->syncWithoutDetaching([
            $administration->id => ['role' => 'lead', 'assignment_status' => 'active', 'joined_at' => now()],
        ]);

        // ── 4. Create Projects ───────────────────────────────────────────────
        $proj1 = Project::firstOrCreate(['slug' => 'restyling-sito-alpha'], [
            'client_id' => $alphaClient->id,
            'name' => 'Restyling Sito Alpha',
            'code' => 'P-ALP-01',
            'description' => 'Rifacimento completo del sito web istituzionale di Alpha Srl con focus su UX, performance tech e nuovo brand.',
            'status' => 'active',
            'start_date' => now()->subDays(30),
            'end_date' => now()->addDays(60),
        ]);
        $proj1->users()->syncWithoutDetaching([
            $admin->id => ['role' => 'sponsor', 'assignment_status' => 'active', 'assigned_at' => now()],
            $developer->id => ['role' => 'lead', 'assignment_status' => 'active', 'assigned_at' => now()],
            $graphicDesigner->id => ['role' => 'member', 'assignment_status' => 'active', 'assigned_at' => now()],
        ]);

        $proj2 = Project::firstOrCreate(['slug' => 'campagna-social-beta'], [
            'client_id' => $betaClient->id,
            'name' => 'Campagna Social Beta',
            'code' => 'P-BET-01',
            'description' => 'Lancio e gestione campagna social media trimestrale per nuovi servizi B2B.',
            'status' => 'active',
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(80),
        ]);
        $proj2->users()->syncWithoutDetaching([
            $admin->id => ['role' => 'sponsor', 'assignment_status' => 'active', 'assigned_at' => now()],
            $marketing->id => ['role' => 'lead', 'assignment_status' => 'active', 'assigned_at' => now()],
            $graphicDesigner->id => ['role' => 'member', 'assignment_status' => 'active', 'assigned_at' => now()],
        ]);

        $proj3 = Project::firstOrCreate(['slug' => 'shooting-catalogo-gamma'], [
            'client_id' => $gammaClient->id,
            'name' => 'Shooting Catalogo Gamma',
            'code' => 'P-GAM-01',
            'description' => 'Casting, shooting e post-produzione per il nuovo catalogo invernale retail Gamma.',
            'status' => 'active',
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(20),
        ]);
        $proj3->users()->syncWithoutDetaching([
            $admin->id => ['role' => 'sponsor', 'assignment_status' => 'active', 'assigned_at' => now()],
            $photographer->id => ['role' => 'lead', 'assignment_status' => 'active', 'assigned_at' => now()],
            $graphicDesigner->id => ['role' => 'member', 'assignment_status' => 'active', 'assigned_at' => now()],
        ]);

        $proj4 = Project::firstOrCreate(['slug' => 'portale-interno-beta'], [
            'client_id' => $betaClient->id,
            'name' => 'Portale Interno Beta',
            'code' => 'P-BET-02',
            'description' => 'Realizzazione nuova intranet per gestione documentale dedicata allo studio Beta.',
            'status' => 'active',
            'start_date' => now()->subDays(60),
            'end_date' => now()->addDays(15),
        ]);
        $proj4->users()->syncWithoutDetaching([
            $admin->id => ['role' => 'sponsor', 'assignment_status' => 'active', 'assigned_at' => now()],
            $developer->id => ['role' => 'lead', 'assignment_status' => 'active', 'assigned_at' => now()],
        ]);

        $proj5 = Project::firstOrCreate(['slug' => 'lancio-brand-alpha'], [
            'client_id' => $alphaClient->id,
            'name' => 'Lancio Brand Alpha',
            'code' => 'P-ALP-02',
            'description' => 'Strategia di go-to-market per la nuova divisione prodotto di Alpha.',
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(90),
        ]);
        $proj5->users()->syncWithoutDetaching([
            $admin->id => ['role' => 'sponsor', 'assignment_status' => 'active', 'assigned_at' => now()],
            $marketing->id => ['role' => 'lead', 'assignment_status' => 'active', 'assigned_at' => now()],
            $photographer->id => ['role' => 'member', 'assignment_status' => 'active', 'assigned_at' => now()],
            $graphicDesigner->id => ['role' => 'member', 'assignment_status' => 'active', 'assigned_at' => now()],
        ]);

        // ── 5. Create Tasks ──────────────────────────────────────────────────
        // Su Restyling Sito Alpha
        Task::create([
            'project_id' => $proj1->id,
            'created_by' => $admin->id,
            'assigned_to' => $developer->id,
            'title' => 'Implementare layout homepage',
            'description' => 'Tradurre in codice il design approvato.',
            'status' => 'in_progress',
            'priority' => 'high',
            'due_date' => now()->addDays(7),
        ]);
        Task::create([
            'project_id' => $proj1->id,
            'created_by' => $admin->id,
            'assigned_to' => $graphicDesigner->id,
            'title' => 'Preparare mockup sezione hero',
            'description' => 'Bozza per animazione hero inclusi i video asset.',
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => now()->addDays(3),
        ]);

        // Su Campagna Social Beta
        Task::create([
            'project_id' => $proj2->id,
            'created_by' => $admin->id,
            'assigned_to' => $marketing->id,
            'title' => 'Pianificare calendario editoriale',
            'description' => 'Definizione uscite LinkedIn e Meta.',
            'status' => 'in_progress',
            'priority' => 'high',
            'due_date' => now()->addDays(2),
        ]);
        Task::create([
            'project_id' => $proj2->id,
            'created_by' => $marketing->id,
            'assigned_to' => $graphicDesigner->id,
            'title' => 'Produrre creatività ads',
            'description' => 'Format square e stories per promozioni B2B.',
            'status' => 'todo',
            'priority' => 'high',
            'due_date' => now()->addDays(5),
        ]);

        // Su Shooting Catalogo Gamma
        Task::create([
            'project_id' => $proj3->id,
            'created_by' => $admin->id,
            'assigned_to' => $photographer->id,
            'title' => 'Preparare shot list',
            'description' => 'Elenco outfit e location previste.',
            'status' => 'todo',
            'priority' => 'high',
            'due_date' => now()->addDays(1),
        ]);
        Task::create([
            'project_id' => $proj3->id,
            'created_by' => $admin->id,
            'assigned_to' => $photographer->id,
            'title' => 'Selezionare foto finali',
            'description' => 'Prima cernita scartando il moscio.',
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => now()->addDays(10),
        ]);
        Task::create([
            'project_id' => $proj3->id,
            'created_by' => $admin->id,
            'assigned_to' => $graphicDesigner->id,
            'title' => 'Impaginare materiali catalogo',
            'description' => 'InDesign layout setup e master pages.',
            'status' => 'in_progress',
            'priority' => 'medium',
            'due_date' => now()->addDays(15),
        ]);

        // Su Portale Interno Beta
        Task::create([
            'project_id' => $proj4->id,
            'created_by' => $admin->id,
            'assigned_to' => $developer->id,
            'title' => 'Configurare autenticazione',
            'description' => 'MFA e permessi.',
            'status' => 'done',
            'priority' => 'urgent',
            'due_date' => now()->subDays(5),
            'completed_at' => now()->subDays(6),
        ]);

        // Su Lancio Brand Alpha
        Task::create([
            'project_id' => $proj5->id,
            'created_by' => $marketing->id,
            'assigned_to' => $graphicDesigner->id,
            'title' => 'Definire key visual',
            'description' => 'Brainstorming sul main asset per il lancio.',
            'status' => 'in_progress',
            'priority' => 'high',
            'due_date' => now()->addDays(4),
        ]);
        Task::create([
            'project_id' => $proj5->id,
            'created_by' => $marketing->id,
            'assigned_to' => $photographer->id,
            'title' => 'Organizzare shooting teaser',
            'description' => 'Breve cut on location per i reels intro.',
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => now()->addDays(6),
        ]);
        Task::create([
            'project_id' => $proj5->id,
            'created_by' => $admin->id,
            'assigned_to' => $developer->id, // Non è un membro diretto su questo progetto nel seed, così testiamo il framework
            'title' => 'Preparare landing lancio',
            'description' => 'One page site per la raccolta lead pre-lancio.',
            'status' => 'todo',
            'priority' => 'high',
            'due_date' => now()->addDays(14),
        ]);

        // ── 6. Create Tickets ────────────────────────────────────────────────
        Ticket::create([
            'client_id' => $alphaClient->id,
            'project_id' => $proj1->id,
            'created_by' => $admin->id,
            'assigned_to' => $developer->id,
            'title' => 'Bug responsive su mobile',
            'description' => 'La griglia si rompe su schermi molto piccoli (in particolare iPhone SE).',
            'type' => 'bug',
            'status' => 'open',
            'priority' => 'high',
        ]);
        Ticket::create([
            'client_id' => $betaClient->id,
            'project_id' => $proj2->id,
            'created_by' => $marketing->id,
            'assigned_to' => $graphicDesigner->id,
            'title' => 'Mancano asset per campagna stories',
            'description' => 'Il batch arrivato non ha la color correction giusta.',
            'type' => 'request',
            'status' => 'open',
            'priority' => 'medium',
        ]);
        Ticket::create([
            'client_id' => $gammaClient->id,
            'project_id' => $proj3->id,
            'created_by' => $admin->id,
            'assigned_to' => $photographer->id,
            'title' => 'Confermare location shooting',
            'description' => 'Attendiamo permesso per lo scenario in villa.',
            'type' => 'support',
            'status' => 'waiting',
            'priority' => 'urgent',
        ]);
        Ticket::create([
            'client_id' => $alphaClient->id,
            'project_id' => $proj5->id,
            'created_by' => $admin->id,
            'assigned_to' => $marketing->id,
            'title' => 'Aggiornare headline pagina teaser',
            'description' => 'Il cliente desidera modificare il claim.',
            'type' => 'change',
            'status' => 'in_progress',
            'priority' => 'medium',
        ]);

        // ── 7. Create Calendar Events ────────────────────────────────────────
        CalendarEvent::create([
            'client_id' => $alphaClient->id,
            'project_id' => $proj1->id,
            'created_by' => $admin->id,
            'assigned_to' => $developer->id,
            'title' => 'Kickoff Restyling Sito Alpha',
            'description' => 'Prima call ufficiale per allineamento team.',
            'type' => 'client_meeting',
            'status' => 'scheduled',
            'start_at' => now()->addDays(2)->hour(10)->minute(30),
            'end_at' => now()->addDays(2)->hour(11)->minute(30),
        ]);
        CalendarEvent::create([
            'client_id' => $betaClient->id,
            'project_id' => $proj2->id,
            'created_by' => $marketing->id,
            'assigned_to' => $graphicDesigner->id,
            'title' => 'Revisione creativa Campagna Beta',
            'description' => 'Check layout su Figma interno.',
            'type' => 'internal_meeting',
            'status' => 'scheduled',
            'start_at' => now()->addDays(1)->hour(14)->minute(0),
            'end_at' => now()->addDays(1)->hour(15)->minute(0),
        ]);
        CalendarEvent::create([
            'client_id' => $gammaClient->id,
            'project_id' => $proj3->id,
            'created_by' => $photographer->id,
            'assigned_to' => $photographer->id,
            'title' => 'Shooting Catalogo Gamma',
            'description' => 'Giornata on set. Rinfresco sul locale coperto.',
            'type' => 'other',
            'status' => 'scheduled',
            'start_at' => now()->addDays(10)->hour(9)->minute(0),
            'end_at' => now()->addDays(10)->hour(18)->minute(0),
            'is_all_day' => true,
        ]);
        CalendarEvent::create([
            'client_id' => $alphaClient->id,
            'project_id' => $proj5->id,
            'created_by' => $marketing->id,
            'assigned_to' => $marketing->id,
            'title' => 'Consegna asset teaser Alpha',
            'description' => 'Invio drop box materiale finale ai distributori PR.',
            'type' => 'delivery',
            'status' => 'scheduled',
            'start_at' => now()->addDays(7)->hour(16)->minute(0),
            'end_at' => now()->addDays(7)->hour(16)->minute(30),
        ]);
        CalendarEvent::create([
            'client_id' => $betaClient->id,
            'project_id' => $proj4->id,
            'created_by' => $developer->id,
            'assigned_to' => $administration->id,
            'title' => 'Review tecnica Portale Beta',
            'description' => 'Sanity check sul cluster.',
            'type' => 'review',
            'status' => 'completed',
            'start_at' => now()->subDays(2)->hour(11)->minute(0),
            'end_at' => now()->subDays(2)->hour(12)->minute(0),
        ]);

        // ── 8. Create Invoices (Finance) ─────────────────────────────────────
        // Invoice 1: Draft - Restyling
        $inv1 = Invoice::create([
            'client_id' => $alphaClient->id,
            'project_id' => $proj1->id,
            'created_by' => $administration->id, // Rigorosamente Administration
            'number' => 'FAT-001',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'draft',
            'currency' => 'EUR',
            'subtotal' => 2500.00,
            'tax_amount' => 550.00,
            'total' => 3050.00,
            'paid_total' => 0.00,
        ]);

        // Invoice 2: Issued - Campagna Social
        $inv2 = Invoice::create([
            'client_id' => $betaClient->id,
            'project_id' => $proj2->id,
            'created_by' => $administration->id,
            'number' => 'FAT-002',
            'issue_date' => now()->subDays(5)->toDateString(),
            'due_date' => now()->addDays(25)->toDateString(),
            'status' => 'issued',
            'currency' => 'EUR',
            'subtotal' => 1800.00,
            'tax_amount' => 396.00,
            'total' => 2196.00,
            'paid_total' => 0.00,
        ]);

        // Invoice 3: Overdue - Shooting
        $inv3 = Invoice::create([
            'client_id' => $gammaClient->id,
            'project_id' => $proj3->id,
            'created_by' => $administration->id,
            'number' => 'FAT-003',
            'issue_date' => now()->subDays(45)->toDateString(),
            'due_date' => now()->subDays(15)->toDateString(),
            'status' => 'overdue',
            'currency' => 'EUR',
            'subtotal' => 3200.00,
            'tax_amount' => 704.00,
            'total' => 3904.00,
            'paid_total' => 0.00,
        ]);

        // Invoice 4: Paid - Portale
        $inv4 = Invoice::create([
            'client_id' => $betaClient->id,
            'project_id' => $proj4->id,
            'created_by' => $administration->id,
            'number' => 'FAT-004',
            'issue_date' => now()->subDays(20)->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'paid',
            'currency' => 'EUR',
            'subtotal' => 4000.00,
            'tax_amount' => 880.00,
            'total' => 4880.00,
            'paid_total' => 4880.00, // already paid
        ]);

        // Invoice 5: Partially Paid - Lancio
        $inv5 = Invoice::create([
            'client_id' => $alphaClient->id,
            'project_id' => $proj5->id,
            'created_by' => $administration->id,
            'number' => 'FAT-005',
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->addDays(20)->toDateString(),
            'status' => 'partially_paid',
            'currency' => 'EUR',
            'subtotal' => 2200.00,
            'tax_amount' => 484.00,
            'total' => 2684.00,
            'paid_total' => 1000.00,
        ]);

        // ── 9. Create Payments ───────────────────────────────────────────────
        Payment::create([
            'invoice_id' => $inv4->id,
            'client_id' => $betaClient->id,
            'project_id' => $proj4->id,
            'created_by' => $administration->id,
            'payment_date' => now()->subDays(5)->toDateString(),
            'amount' => 4880.00,
            'method' => 'bank_transfer',
            'reference' => 'TRN-998877',
            'notes' => 'Pagato in toto.',
        ]);

        Payment::create([
            'invoice_id' => $inv5->id,
            'client_id' => $alphaClient->id,
            'project_id' => $proj5->id,
            'created_by' => $administration->id,
            'payment_date' => now()->subDays(2)->toDateString(),
            'amount' => 1000.00,
            'method' => 'bank_transfer',
            'reference' => 'TRN-554422',
            'notes' => 'Acconto primo step operativo.',
        ]);

        // ── 10. Create Marketing Campaigns ─────────────────────────────────────
        $campaign1 = MarketingCampaign::create([
            'client_id' => $betaClient->id,
            'name' => 'Campagna Estiva 2026',
            'status' => 'active',
            'description' => 'Lancio nuovi servizi estivi sui social.',
            'created_by' => $marketing->id,
            'starts_at' => now()->startOfMonth()->toDateString(),
            'ends_at' => now()->addMonths(3)->endOfMonth()->toDateString(),
            'monthly_fee' => 1500.00,
        ]);

        $period1 = MarketingCampaignPeriod::create([
            'marketing_campaign_id' => $campaign1->id,
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->endOfMonth()->toDateString(),
            'amount' => 1500.00,
            'status' => 'active',
        ]);

        MarketingCampaignPost::create([
            'marketing_campaign_id' => $campaign1->id,
            'title' => 'Post Lancio Estivo',
            'description' => 'Siamo pronti per l\'estate con i nostri nuovi servizi! Scopri le promozioni in corso.',
            'status' => 'approved',
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'scheduled_time' => '10:00:00',
        ]);

        MarketingCampaignPost::create([
            'marketing_campaign_id' => $campaign1->id,
            'title' => 'Post B2B LinkedIn',
            'description' => 'Come ottimizzare le vendite nel periodo estivo: ecco la nostra guida B2B.',
            'status' => 'draft',
        ]);

        // ── 11. Create Hosting Services ───────────────────────────────────────
        $domain1 = \App\Models\HostingService::create([
            'client_id' => $alphaClient->id,
            'type' => 'domain',
            'name' => 'Dominio Principale Alpha',
            'domain' => 'alphasrl.it',
            'provider' => 'Aruba',
            'location' => 'Arezzo IT',
            'status' => 'active',
            'access_url' => 'https://admin.aruba.it',
            'username' => 'admin_alpha',
            'password' => 'secret_aruba_123!',
            'renewal_date' => now()->addMonths(2)->toDateString(),
            'renewal_cost' => 15.00,
            'billing_cycle' => 'yearly',
            'notes' => 'Registrato originariamente nel 2018.',
        ]);

        \App\Models\HostingService::create([
            'client_id' => $betaClient->id,
            'type' => 'domain',
            'name' => 'Dominio Beta Studio',
            'domain' => 'betastudio.com',
            'provider' => 'Register',
            'status' => 'active',
            'username' => 'beta_reg',
            'password' => 'B3t4$tud10!',
            'renewal_date' => now()->addMonths(8)->toDateString(),
            'renewal_cost' => 25.00,
        ]);

        $hosting1 = \App\Models\HostingService::create([
            'client_id' => $alphaClient->id,
            'type' => 'hosting',
            'name' => 'Server Dedicato Alpha',
            'domain' => 'alphasrl.it',
            'provider' => 'SiteGround',
            'status' => 'active',
            'access_url' => 'https://tools.siteground.com',
            'username' => 'tech@alphasrl.it',
            'password' => 'SG_alpha_2026',
            'renewal_date' => now()->addMonths(5)->toDateString(),
            'renewal_cost' => 150.00,
            'notes' => 'Server Cloud 4 Core, 8GB RAM.',
        ]);

        $maint1 = \App\Models\HostingService::create([
            'client_id' => $gammaClient->id,
            'type' => 'maintenance',
            'name' => 'Manutenzione E-commerce Gamma',
            'domain' => 'shop.gamma.it',
            'provider' => 'Interno',
            'status' => 'active',
            'renewal_date' => now()->addMonths(1)->toDateString(),
            'renewal_cost' => 800.00,
            'billing_cycle' => 'yearly',
            'notes' => 'Include 10 ore/mese di interventi e aggiornamenti plugin WooCommerce.',
        ]);

        \App\Models\HostingServiceIntervention::create([
            'hosting_service_id' => $maint1->id,
            'user_id' => $developer->id,
            'title' => 'Aggiornamento Major WooCommerce',
            'description' => 'Aggiornamento alla versione 9.0 con fix template checkout.',
            'intervention_date' => now()->subDays(5)->toDateString(),
        ]);

        \App\Models\HostingServiceIntervention::create([
            'hosting_service_id' => $hosting1->id,
            'user_id' => $admin->id,
            'title' => 'Upgrade RAM Server',
            'description' => 'Passaggio da 4GB a 8GB su richiesta cliente causa picchi traffico.',
            'intervention_date' => now()->subDays(20)->toDateString(),
            'cost' => 50.00,
        ]);
    }
}
