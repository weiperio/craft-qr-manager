<?php

namespace weiperio\craftqrmanager\elements\db;

use craft\helpers\Db;
use craft\elements\db\ElementQuery;

use yii\db\Expression;

use weiperio\craftqrmanager\db\Table;

/**
 * Route query
 */
class RouteQuery extends ElementQuery
{
    public $entryUri;
    public $redirectUri;

    public function entryUri($value): self
    {
        $this->entryUri = $value;

        return $this;
    }

    public function redirectUri($value): self
    {
        $this->redirectUri = $value;

        return $this;
    }
    protected function beforePrepare(): bool
    {
        $this->joinElementTable(Table::ROUTES);

        $alias = Db::rawTableShortName(Table::ROUTES);

        $this->query->select([
            $alias . '.entryUri',
            $alias . '.redirectUri',
        ]);

        if ($this->entryUri) {
            $entryUri = $this->entryUri;

            $this->subQuery->andWhere(new Expression("`entryUri` REGEXP :entryUri", [':entryUri' => "^/?{$entryUri}"]));
        }

        if ($this->redirectUri) {
            $redirectUri = $this->redirectUri;
            $this->subQuery->andWhere(new Expression("`redirectUri` REGEXP :redirectUri", [':redirectUri' => "^/?{$redirectUri}"]));
        }

        return parent::beforePrepare();
    }
}
