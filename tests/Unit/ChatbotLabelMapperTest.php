<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Domain\Chatbot\Support\ChatbotLabelMapper;

class ChatbotLabelMapperTest extends TestCase
{
    public function test_maps_tickets_correctly()
    {
        $this->assertEquals('In lavorazione', ChatbotLabelMapper::status('in_progress'));
        $this->assertEquals('Alta', ChatbotLabelMapper::priority('high'));
        $this->assertEquals('Richiesta di modifica', ChatbotLabelMapper::ticketType('change_request'));
    }

    public function test_maps_marketing_posts_correctly()
    {
        $this->assertEquals('Generato', ChatbotLabelMapper::status('generated'));
        $this->assertEquals('Immagine', ChatbotLabelMapper::contentType('image'));
        
        $platforms = ChatbotLabelMapper::platforms(['instagram', 'facebook']);
        $this->assertEquals(['Instagram', 'Facebook'], $platforms);
    }

    public function test_fallback_works_correctly()
    {
        $this->assertEquals('Unmapped Custom Status', ChatbotLabelMapper::status('unmapped_custom_status'));
    }
}
