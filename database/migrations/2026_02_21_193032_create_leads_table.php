<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            
            // Identificadores Básicos
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id')->nullable();
            
            // Dados da Empresa / Contato
            $table->string('name');
            $table->string('surname')->nullable()->comment('Nome Fantasia');
            $table->string('document')->nullable()->comment('CPF ou CNPJ');
            $table->string('cnae_code')->nullable();
            $table->string('cnae_name')->nullable();
            
            // Contatos
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            
            // Endereço (Será preenchido via ViaCEP)
            $table->string('zip_code')->nullable();
            $table->string('address')->nullable();
            $table->string('number')->nullable();
            $table->string('complement')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            
            // Geolocalização (Será preenchido via Nominatim)
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // Chaves Estrangeiras (Taxonomias e Vendedor)
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->foreignId('lead_status_id')->nullable()->constrained('lead_statuses')->nullOnDelete();
            $table->foreignId('lead_potential_id')->nullable()->constrained('lead_potentials')->nullOnDelete();
            $table->foreignId('lead_source_id')->nullable()->constrained('lead_sources')->nullOnDelete();
            
            // Controle de Follow-up
            $table->text('notes')->nullable();
            $table->text('last_follow_up_note')->nullable();
            $table->datetime('last_follow_up_date')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};