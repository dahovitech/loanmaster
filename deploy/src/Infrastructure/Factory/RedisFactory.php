<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use Psr\Log\LoggerInterface;
use Redis;
use RedisCluster;

/**
 * Factory pour créer des connexions Redis optimisées
 */
class RedisFactory
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Crée une instance Redis avec configuration optimisée
     */
    public static function create(string $dsn, array $options = []): Redis
    {
        $redis = new Redis();
        
        // Parser le DSN
        $parsedUrl = parse_url($dsn);
        $host = $parsedUrl['host'] ?? 'localhost';
        $port = $parsedUrl['port'] ?? 6379;
        $password = $parsedUrl['pass'] ?? null;
        $database = ltrim($parsedUrl['path'] ?? '0', '/');

        // Options par défaut
        $defaultOptions = [
            'persistent' => false,
            'timeout' => 5.0,
            'read_timeout' => 5.0,
            'tcp_keepalive' => 1,
            'serializer' => Redis::SERIALIZER_IGBINARY,
            'compression' => Redis::COMPRESSION_LZ4,
            'compression_level' => 6
        ];

        $options = array_merge($defaultOptions, $options);

        try {
            // Connexion avec ou sans persistance
            if ($options['persistent']) {
                $redis->pconnect($host, $port, $options['timeout']);
            } else {
                $redis->connect($host, $port, $options['timeout']);
            }

            // Configuration des timeouts
            $redis->setOption(Redis::OPT_READ_TIMEOUT, $options['read_timeout']);
            $redis->setOption(Redis::OPT_TCP_KEEPALIVE, $options['tcp_keepalive']);

            // Configuration de la sérialisation
            if ($options['serializer']) {
                $redis->setOption(Redis::OPT_SERIALIZER, $options['serializer']);
            }

            // Configuration de la compression
            if ($options['compression']) {
                $redis->setOption(Redis::OPT_COMPRESSION, $options['compression']);
                $redis->setOption(Redis::OPT_COMPRESSION_LEVEL, $options['compression_level']);
            }

            // Authentification si nécessaire
            if ($password) {
                $redis->auth($password);
            }

            // Sélection de la base de données
            $redis->select((int) $database);

            // Test de connexion
            $redis->ping();

        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to connect to Redis: {$e->getMessage()}", 0, $e);
        }

        return $redis;
    }

    /**
     * Crée une instance Redis Cluster
     */
    public static function createCluster(array $nodes, array $options = []): RedisCluster
    {
        $defaultOptions = [
            'connection_timeout' => 5.0,
            'read_timeout' => 5.0,
            'persistent' => false,
            'password' => null,
            'serializer' => Redis::SERIALIZER_IGBINARY,
            'compression' => Redis::COMPRESSION_LZ4
        ];

        $options = array_merge($defaultOptions, $options);

        try {
            $cluster = new RedisCluster(
                null,
                $nodes,
                $options['connection_timeout'],
                $options['read_timeout'],
                $options['persistent'],
                $options['password']
            );

            // Configuration post-connexion
            if ($options['serializer']) {
                $cluster->setOption(RedisCluster::OPT_SERIALIZER, $options['serializer']);
            }

            if ($options['compression']) {
                $cluster->setOption(RedisCluster::OPT_COMPRESSION, $options['compression']);
            }

        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to connect to Redis Cluster: {$e->getMessage()}", 0, $e);
        }

        return $cluster;
    }

    /**
     * Crée une connexion Redis avec pool de connexions
     */
    public static function createPool(string $dsn, int $poolSize = 5): array
    {
        $pool = [];
        
        for ($i = 0; $i < $poolSize; $i++) {
            $pool[] = self::create($dsn, ['persistent' => true]);
        }
        
        return $pool;
    }

    /**
     * Crée une connexion Redis avec sentinel pour haute disponibilité
     */
    public static function createWithSentinel(array $sentinels, string $service, array $options = []): Redis
    {
        $defaultOptions = [
            'timeout' => 5.0,
            'read_timeout' => 5.0,
            'password' => null
        ];

        $options = array_merge($defaultOptions, $options);

        foreach ($sentinels as $sentinel) {
            try {
                $sentinelConnection = new Redis();
                $sentinelConnection->connect($sentinel['host'], $sentinel['port'], $options['timeout']);

                // Demander l'adresse du master au sentinel
                $masterInfo = $sentinelConnection->rawCommand('SENTINEL', 'get-master-addr-by-name', $service);
                
                if ($masterInfo && count($masterInfo) >= 2) {
                    $masterHost = $masterInfo[0];
                    $masterPort = (int) $masterInfo[1];
                    
                    // Se connecter au master
                    $redis = new Redis();
                    $redis->connect($masterHost, $masterPort, $options['timeout']);
                    
                    if ($options['password']) {
                        $redis->auth($options['password']);
                    }
                    
                    return $redis;
                }
                
            } catch (\Exception $e) {
                // Essayer le sentinel suivant
                continue;
            }
        }

        throw new \RuntimeException('Failed to connect to Redis via Sentinel');
    }

    /**
     * Valide la configuration Redis
     */
    public static function validateConfiguration(array $config): array
    {
        $errors = [];

        // Vérifier la présence des paramètres requis
        if (empty($config['host'])) {
            $errors[] = 'Redis host is required';
        }

        if (empty($config['port']) || !is_numeric($config['port'])) {
            $errors[] = 'Valid Redis port is required';
        }

        // Vérifier les timeouts
        if (isset($config['timeout']) && (!is_numeric($config['timeout']) || $config['timeout'] <= 0)) {
            $errors[] = 'Timeout must be a positive number';
        }

        // Vérifier les options de sérialisation
        if (isset($config['serializer'])) {
            $validSerializers = [
                Redis::SERIALIZER_NONE,
                Redis::SERIALIZER_PHP,
                Redis::SERIALIZER_IGBINARY,
                Redis::SERIALIZER_MSGPACK
            ];
            
            if (!in_array($config['serializer'], $validSerializers, true)) {
                $errors[] = 'Invalid serializer option';
            }
        }

        // Vérifier les options de compression
        if (isset($config['compression'])) {
            $validCompression = [
                Redis::COMPRESSION_NONE,
                Redis::COMPRESSION_LZF,
                Redis::COMPRESSION_ZSTD,
                Redis::COMPRESSION_LZ4
            ];
            
            if (!in_array($config['compression'], $validCompression, true)) {
                $errors[] = 'Invalid compression option';
            }
        }

        return $errors;
    }

    /**
     * Obtient les recommandations de configuration selon l'environnement
     */
    public static function getRecommendedConfig(string $environment): array
    {
        return match ($environment) {
            'prod' => [
                'persistent' => true,
                'timeout' => 3.0,
                'read_timeout' => 3.0,
                'tcp_keepalive' => 1,
                'serializer' => Redis::SERIALIZER_IGBINARY,
                'compression' => Redis::COMPRESSION_LZ4,
                'compression_level' => 6
            ],
            'dev' => [
                'persistent' => false,
                'timeout' => 5.0,
                'read_timeout' => 5.0,
                'tcp_keepalive' => 0,
                'serializer' => Redis::SERIALIZER_PHP,
                'compression' => Redis::COMPRESSION_NONE
            ],
            'test' => [
                'persistent' => false,
                'timeout' => 1.0,
                'read_timeout' => 1.0,
                'tcp_keepalive' => 0,
                'serializer' => Redis::SERIALIZER_NONE,
                'compression' => Redis::COMPRESSION_NONE
            ],
            default => throw new \InvalidArgumentException("Unknown environment: {$environment}")
        };
    }
}
