<?php
declare(strict_types=1);

class Migration021GeoStudioAddressPacks
{
    public function up(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_address_packs (
                pack_id TEXT PRIMARY KEY,
                pack_type TEXT NOT NULL CHECK (pack_type IN ('national', 'custom')),
                code_label TEXT NOT NULL,
                default_language TEXT NOT NULL,
                languages_json TEXT NOT NULL,
                root_names_json TEXT NOT NULL,
                levels_json TEXT NOT NULL,
                version TEXT NOT NULL,
                release_date TEXT NOT NULL,
                last_updated TEXT NOT NULL,
                author TEXT NOT NULL,
                contributors_json TEXT NOT NULL,
                license TEXT NOT NULL,
                sources_json TEXT NOT NULL,
                notes TEXT NOT NULL
            )"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_address_places (
                id " . DbSql::autoIncrement() . ",
                pack_id TEXT NOT NULL,
                parent_id " . DbSql::intType() . ",
                level " . DbSql::intType() . " NOT NULL,
                type_key TEXT NOT NULL,
                code TEXT,
                FOREIGN KEY(pack_id) REFERENCES zimrx_user_address_packs(pack_id) ON DELETE CASCADE,
                UNIQUE(pack_id, id),
                FOREIGN KEY(pack_id, parent_id) REFERENCES zimrx_user_address_places(pack_id, id) ON DELETE CASCADE
            )"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zimrx_user_address_place_names (
                place_id " . DbSql::intType() . " NOT NULL,
                language VARCHAR(35) NOT NULL,
                name TEXT NOT NULL,
                PRIMARY KEY(place_id, language),
                FOREIGN KEY(place_id) REFERENCES zimrx_user_address_places(id) ON DELETE CASCADE
            )"
        );
        $pdo->exec('CREATE INDEX idx_user_address_places_pack_parent ON zimrx_user_address_places(pack_id, parent_id)');
        $pdo->exec('CREATE INDEX idx_user_address_places_pack_level_type ON zimrx_user_address_places(pack_id, level, type_key)');
        $pdo->exec('CREATE INDEX idx_user_address_place_names_search ON zimrx_user_address_place_names(language, name, place_id)');
    }
}
