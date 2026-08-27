<?php

use Illuminate\Support\Facades\Storage;
use App\Models\SupportTicket;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    Storage::fake('azure');

    $this->doctor = createUserWithType('doctor', 'doctor@test.com');
    $this->patient = createUserWithType('patient', 'patient@test.com');
});

it('allows doctor to create support ticket successfully', function () {
        $response = $this->actingAs($this->doctor, 'sanctum')
            ->postJson(route('support.create'), [
                'category' => 'technical',
                'urgency' => 'high',
                'message' => 'Test message for support',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Support message submitted successfully we will get back to you shortly.',
            ]);

        expect(SupportTicket::count())->toBe(1);
});

it('allows doctor to upload attachment and stores it correctly', function () {
        $file = UploadedFile::fake()->create('report.pdf', 500);

        $response = $this->actingAs($this->doctor, 'sanctum')
            ->postJson(route('support.create'), [
                'category' => 'billing',
                'urgency' => 'medium',
                'message' => 'Testing file upload',
                'attachment' => $file,
            ]);

        $response->assertStatus(201);

        $ticket = SupportTicket::first();
        expect($ticket->attachment_path)->not->toBeNull();

        Storage::disk('azure')->assertExists($ticket->attachment_path);
});