<?php

declare(strict_types=1);

/*
 * This file is part of gpupo/common-schema created by Gilmar Pupo <contact@gpupo.com>
 * For the information of copyright and license you should read the file LICENSE which is
 * distributed with this source code. For more information, see <https://opensource.gpupo.com/>
 */

namespace Gpupo\CommonSchema\ORM\EntityRepository\Application\API\OAuth\Client;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Gpupo\CommonSchema\ORM\Entity\Application\API\OAuth\Client\AccessToken;
use Gpupo\CommonSchema\ORM\Entity\Application\API\OAuth\Provider;

/**
 * ClientRepository.
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ClientRepository extends \Gpupo\CommonSchema\ORM\EntityRepository\AbstractEntityRepository
{
    public function getFindByProviderNameQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('u');
        $queryBuilder
            ->andWhere('u.enabled=1')
            ->leftJoin(AccessToken::class, 'token', Join::WITH, $queryBuilder->expr()->eq('token.client', 'u'))
            ->innerJoin(
                Provider::class,
                'provider',
                Join::WITH,
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq('provider.name', ':providerName'),
                    $queryBuilder->expr()->eq('provider', 'u.provider')
                )
            );

        return $queryBuilder;
    }
}
