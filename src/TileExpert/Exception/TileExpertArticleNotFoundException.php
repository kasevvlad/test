<?php

namespace App\TileExpert\Exception;

class TileExpertArticleNotFoundException extends \RuntimeException
{
    public function __construct(string $factory, string $collection, string $article)
    {
        parent::__construct(sprintf(
            'Article "%s/%s/a/%s" was not found on tile.expert',
            $factory,
            $collection,
            $article
        ));
    }
}
