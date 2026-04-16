<?php

use App\Models\CppProvider;
use App\Models\CppProviderCoordinator;
use App\Models\CppProviderNetworkType;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Cache::flush();
    $this->user = User::factory()->create();

    // Provider 1: HIV ecosystem (R0216), private, with email + coordinator
    $hiv = CppProvider::create([
        'hcode' => 'TEST001',
        'name' => 'มูลนิธิทดสอบ HIV',
        'affiliation' => 'เอกชน',
        'province' => 'เชียงใหม่',
        'district' => 'เมืองเชียงใหม่',
        'phone' => '0811111111',
        'uc_email' => 'test@hivtest.org',
    ]);
    CppProviderNetworkType::create([
        'cpp_provider_id' => $hiv->id,
        'type_code' => 'R0216',
        'type_name' => 'หน่วยบริการที่รับการส่งต่อเฉพาะด้านบริการเอชไอวี',
    ]);
    CppProviderCoordinator::create([
        'cpp_provider_id' => $hiv->id,
        'name' => 'ผู้ประสานงาน 1',
        'email' => 'coord@test.org',
    ]);

    // Provider 2: Regular clinic (R020602), no email, no coordinator
    $lab = CppProvider::create([
        'hcode' => 'TEST002',
        'name' => 'คลินิกเทคนิคการแพทย์ทดสอบ',
        'affiliation' => 'เอกชน',
        'province' => 'กรุงเทพมหานคร',
        'district' => 'บางรัก',
        'phone' => '0822222222',
    ]);
    CppProviderNetworkType::create([
        'cpp_provider_id' => $lab->id,
        'type_code' => 'R020602',
        'type_name' => 'หน่วยบริการที่รับการส่งต่อเฉพาะด้านเทคนิคการแพทย์กรณี LAB ทั่วไป',
    ]);

    // Provider 3: Government, different province
    $gov = CppProvider::create([
        'hcode' => 'TEST003',
        'name' => 'โรงพยาบาลทดสอบ',
        'affiliation' => 'รัฐในสธ.(สังกัด สป.)',
        'province' => 'ขอนแก่น',
        'district' => 'เมืองขอนแก่น',
        'phone' => '0833333333',
    ]);
    CppProviderNetworkType::create([
        'cpp_provider_id' => $gov->id,
        'type_code' => 'R0207',
        'type_name' => 'หน่วยบริการที่รับการส่งต่อเฉพาะด้านเวชกรรม',
    ]);
});

test('unauthenticated user cannot view cpp providers', function () {
    $this->get('/cpp-providers')
        ->assertRedirect('/login');
});

test('authenticated user sees all 3 providers by default', function () {
    $this->actingAs($this->user)
        ->get('/cpp-providers')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CppProviders/Index')
            ->has('providers.data', 3)
        );
});

test('search filter by name works', function () {
    $this->actingAs($this->user)
        ->get('/cpp-providers?q='.urlencode('HIV'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('providers.data', 1)
            ->where('providers.data.0.hcode', 'TEST001')
        );
});

test('search filter by hcode works', function () {
    $this->actingAs($this->user)
        ->get('/cpp-providers?q=TEST002')
        ->assertInertia(fn (Assert $page) => $page
            ->has('providers.data', 1)
            ->where('providers.data.0.hcode', 'TEST002')
        );
});

test('province filter works', function () {
    $this->actingAs($this->user)
        ->get('/cpp-providers?province='.urlencode('เชียงใหม่'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('providers.data', 1)
            ->where('providers.data.0.hcode', 'TEST001')
        );
});

test('affiliation filter works', function () {
    $this->actingAs($this->user)
        ->get('/cpp-providers?affiliation='.urlencode('เอกชน'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('providers.data', 2)
        );
});

test('type_code filter isolates R0216 providers', function () {
    $this->actingAs($this->user)
        ->get('/cpp-providers?type_code=R0216')
        ->assertInertia(fn (Assert $page) => $page
            ->has('providers.data', 1)
            ->where('providers.data.0.hcode', 'TEST001')
        );
});

test('has_email filter excludes providers without email', function () {
    $this->actingAs($this->user)
        ->get('/cpp-providers?has_email=1')
        ->assertInertia(fn (Assert $page) => $page
            ->has('providers.data', 1)
            ->where('providers.data.0.hcode', 'TEST001')
        );
});

test('has_coordinator filter excludes providers without coordinators', function () {
    $this->actingAs($this->user)
        ->get('/cpp-providers?has_coordinator=1')
        ->assertInertia(fn (Assert $page) => $page
            ->has('providers.data', 1)
            ->where('providers.data.0.hcode', 'TEST001')
        );
});

test('hiv_only filter includes R0216 providers', function () {
    $this->actingAs($this->user)
        ->get('/cpp-providers?hiv_only=1')
        ->assertInertia(fn (Assert $page) => $page
            ->has('providers.data', 1)
            ->where('providers.data.0.hcode', 'TEST001')
        );
});

test('multiple filters combine with AND logic', function () {
    $this->actingAs($this->user)
        ->get('/cpp-providers?province='.urlencode('กรุงเทพมหานคร').'&affiliation='.urlencode('เอกชน'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('providers.data', 1)
            ->where('providers.data.0.hcode', 'TEST002')
        );
});

test('show endpoint returns provider detail with relations', function () {
    $this->actingAs($this->user)
        ->get('/cpp-providers/TEST001')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CppProviders/Show')
            ->where('provider.hcode', 'TEST001')
            ->has('provider.network_types', 1)
            ->has('provider.coordinators', 1)
        );
});

test('show returns 404 for unknown hcode', function () {
    $this->actingAs($this->user)
        ->get('/cpp-providers/NOTFOUND')
        ->assertNotFound();
});
