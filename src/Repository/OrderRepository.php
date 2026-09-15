<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

class OrderRepository extends ServiceEntityRepository
{
    private const PERIOD_FORMATS = [
        'day' => 'YYYY-MM-DD',
        'month' => 'YYYY-MM',
        'year' => 'YYYY',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function countGroupedByPeriod(string $group, int $limit, int $offset): array
    {
        $format = self::PERIOD_FORMATS[$group];

        $sql = <<<'SQL'
            SELECT period, cnt, COUNT(*) OVER() AS total_groups
            FROM (
                SELECT TO_CHAR(created_at, :format) AS period, COUNT(*) AS cnt
                FROM orders
                GROUP BY period
            ) grouped
            ORDER BY period DESC
            LIMIT :limit OFFSET :offset
            SQL;

        $result = $this->getEntityManager()->getConnection()->executeQuery(
            $sql,
            ['format' => $format, 'limit' => $limit, 'offset' => $offset],
            ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER]
        )->fetchAllAssociative();

        $total = isset($result[0]) ? (int) $result[0]['total_groups'] : 0;

        $rows = array_map(
            static fn (array $row) => ['period' => $row['period'], 'count' => (int) $row['cnt']],
            $result
        );

        return ['total' => $total, 'rows' => $rows];
    }
}
