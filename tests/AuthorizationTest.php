<?php

final class AuthorizationTest extends TestCase
{
    public function testEmptySessionIsUnauthenticated(): void
    {
        $this->assertSame(
            'unauthenticated',
            checkAdminAuthorization([])
        );
    }

    public function testCustomerIsForbidden(): void
    {
        $this->assertSame(
            'forbidden',
            checkAdminAuthorization([
                'user_id' => 2,
                'role' => 'customer',
            ])
        );
    }

    public function testAdminIsAuthorized(): void
    {
        $this->assertSame(
            'ok',
            checkAdminAuthorization([
                'user_id' => 1,
                'role' => 'admin',
            ])
        );
    }

    public function testSessionWithoutRoleIsForbidden(): void
    {
        $this->assertSame(
            'forbidden',
            checkAdminAuthorization([
                'user_id' => 1,
            ])
        );
    }
}