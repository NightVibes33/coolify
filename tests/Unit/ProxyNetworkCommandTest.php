<?php

use App\Models\Server;

function proxyNetworkCommandTestServer(bool $isSwarm = false, array $standaloneDockers = [], array $swarmDockers = []): Server
{
    $server = Mockery::mock(Server::class);
    $server->shouldReceive('isSwarm')->andReturn($isSwarm);
    $server->shouldReceive('getSchemalessAttributes')->andReturn([]);
    $server->shouldReceive('getAttribute')->with('standaloneDockers')->andReturn(collect($standaloneDockers));
    $server->shouldReceive('getAttribute')->with('swarmDockers')->andReturn(collect($swarmDockers));

    return $server;
}

it('builds standalone Docker network creation with IPv6 fallback', function () {
    $command = dockerNetworkCreateCommand('coolify', preferIpv6: true);

    expect($command)->toBe("(docker network create --ipv6 --attachable 'coolify' 2>/dev/null || docker network create --attachable 'coolify')");
});

it('builds quiet standalone Docker network creation with IPv6 fallback', function () {
    $command = dockerNetworkCreateCommand('coolify', preferIpv6: true, suppressOutput: true);

    expect($command)->toBe("(docker network create --ipv6 --attachable 'coolify' >/dev/null 2>&1 || docker network create --attachable 'coolify' >/dev/null 2>&1)");
});

it('does not add IPv6 flags to swarm overlay networks', function () {
    $command = dockerNetworkCreateCommand('coolify-overlay', isSwarm: true, preferIpv6: true);

    expect($command)
        ->toBe("docker network create --driver overlay --attachable 'coolify-overlay'")
        ->not->toContain('--ipv6');
});

it('warns when an existing proxy network is IPv4-only', function () {
    $command = dockerNetworkEnsureCommand('coolify', preferIpv6: true, warnIfIpv4Only: true, suppressOutput: true);

    expect($command)
        ->toContain("docker network inspect --format '{{.EnableIPv6}}' 'coolify'")
        ->toContain('exists without IPv6')
        ->toContain('X-Forwarded-For');
});

it('uses the default coolify network as a standalone proxy ingress network', function () {
    $server = proxyNetworkCommandTestServer();

    expect(shouldCreateProxyNetworkWithIpv6($server, 'coolify'))->toBeTrue()
        ->and(shouldCreateProxyNetworkWithIpv6($server, 'app-network'))->toBeFalse();
});

it('prefers IPv6 only for standalone networks used by the proxy compose file', function () {
    $server = proxyNetworkCommandTestServer(standaloneDockers: [
        ['network' => 'frontend'],
    ]);

    expect(shouldCreateProxyNetworkWithIpv6($server, 'frontend'))->toBeTrue()
        ->and(shouldCreateProxyNetworkWithIpv6($server, 'worker'))->toBeFalse();
});

it('does not request IPv6 for swarm proxy ingress networks', function () {
    $server = proxyNetworkCommandTestServer(isSwarm: true, swarmDockers: [
        ['network' => 'coolify-overlay'],
    ]);

    expect(shouldCreateProxyNetworkWithIpv6($server, 'coolify-overlay'))->toBeFalse();
});
