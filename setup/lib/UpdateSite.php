<?php

namespace IQuix\Setup;

defined('_JEXEC') or die('Unauthorized Access');

use Joomla\CMS\Factory;

/**
 * Points Joomla's updater at the FluentCart server and hands it the
 * credentials the download endpoint requires.
 */
final class UpdateSite
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreInterface $store,
        private readonly string $siteUrl
    ) {
    }

    /**
     * The extra_query Joomla appends to the download URL. Empty when there is
     * no license, so a free site does not advertise blank credentials.
     */
    public static function extraQuery(Config $config, StoreInterface $store, string $siteUrl): string
    {
        $key = $store->get('license_key');

        if ($key === '') {
            return '';
        }

        return http_build_query([
            'license_key'     => $key,
            'activation_hash' => $store->get('activation_hash'),
            'item_id'         => $config->itemId(),
            'site_url'        => $siteUrl,
        ]);
    }

    public function apply(): void
    {
        $db          = Factory::getDbo();
        $location    = $this->config->proUpdateXmlUrl();
        $extraQuery  = self::extraQuery($this->config, $this->store, $this->siteUrl);
        $extensionId = $this->extensionId('pkg_quix');

        $this->purgeRetiredSites();

        $query = $db->getQuery(true)
            ->select($db->quoteName('update_site_id'))
            ->from($db->quoteName('#__update_sites'))
            ->where($db->quoteName('name') . ' = ' . $db->quote(Config::UPDATE_SITE_NAME));
        $db->setQuery($query);
        $updateSiteId = (int) $db->loadResult();

        if ($updateSiteId > 0) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__update_sites'))
                ->set($db->quoteName('location') . ' = ' . $db->quote($location))
                ->set($db->quoteName('extra_query') . ' = ' . $db->quote($extraQuery))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('update_site_id') . ' = ' . $updateSiteId);
            $db->setQuery($query);
            $db->execute();
        } else {
            $row = (object) [
                'name'                 => Config::UPDATE_SITE_NAME,
                'type'                 => 'extension',
                'location'             => $location,
                'enabled'              => 1,
                'extra_query'          => $extraQuery,
                'last_check_timestamp' => 0,
            ];
            $db->insertObject('#__update_sites', $row);
            $updateSiteId = (int) $db->insertid();
        }

        if ($updateSiteId > 0 && $extensionId > 0) {
            $this->link($updateSiteId, $extensionId);
        }

        Log::debug('Update site set to ' . $location);
    }

    /**
     * The names older iQuix releases gave Quix's own update site. `Quix` is
     * what setup/controllers/update.php wrote before the rewrite.
     */
    private const LEGACY_SITE_NAMES = ['Quix', Config::UPDATE_SITE_NAME];

    /**
     * Drop any update site still pointing at the retired ThemeXpert API, so a
     * site upgraded from an older iQuix stops asking a dead server.
     *
     * Scoped to Quix's own rows. Matching every `%themexpert.com%` location
     * deleted the update sites of any *other* ThemeXpert extension on the
     * site, permanently and silently.
     */
    private function purgeRetiredSites(): void
    {
        $db = Factory::getDbo();

        // Quix's own extension rows, so a retired site that was linked to one
        // of them is caught even if it was never named "Quix".
        $quixExtensionIds = array_values(array_filter([
            $this->extensionId('pkg_quix'),
            $this->extensionId('com_quix'),
        ]));

        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('s.update_site_id'))
            ->from($db->quoteName('#__update_sites', 's'))
            ->leftJoin(
                $db->quoteName('#__update_sites_extensions', 'se')
                . ' ON ' . $db->quoteName('se.update_site_id') . ' = ' . $db->quoteName('s.update_site_id')
            )
            ->where($db->quoteName('s.location') . ' LIKE ' . $db->quote('%themexpert.com%'));

        $ownership = [
            $db->quoteName('s.name') . ' IN (' . implode(
                ',',
                array_map([$db, 'quote'], self::LEGACY_SITE_NAMES)
            ) . ')',
        ];

        if ($quixExtensionIds !== []) {
            $ownership[] = $db->quoteName('se.extension_id')
                . ' IN (' . implode(',', $quixExtensionIds) . ')';
        }

        $query->where('(' . implode(' OR ', $ownership) . ')');

        $db->setQuery($query);

        $ids = array_map('intval', (array) $db->loadColumn());

        if ($ids === []) {
            return;
        }

        foreach (['#__update_sites_extensions', '#__update_sites'] as $table) {
            $delete = $db->getQuery(true)
                ->delete($db->quoteName($table))
                ->whereIn($db->quoteName('update_site_id'), $ids);
            $db->setQuery($delete);
            $db->execute();
        }

        Log::debug('Removed ' . count($ids) . ' retired ThemeXpert update site(s)');
    }

    public function purgeUpdates(): void
    {
        $extensionId = $this->extensionId('pkg_quix');

        if ($extensionId === 0) {
            return;
        }

        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__updates'))
            ->where($db->quoteName('extension_id') . ' = ' . $extensionId);
        $db->setQuery($query);
        $db->execute();
    }

    private function link(int $updateSiteId, int $extensionId): void
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__update_sites_extensions'))
            ->where($db->quoteName('update_site_id') . ' = ' . $updateSiteId)
            ->where($db->quoteName('extension_id') . ' = ' . $extensionId);
        $db->setQuery($query);

        if ((int) $db->loadResult() > 0) {
            return;
        }

        $db->insertObject('#__update_sites_extensions', (object) [
            'update_site_id' => $updateSiteId,
            'extension_id'   => $extensionId,
        ]);
    }

    private function extensionId(string $element): int
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote($element));
        $db->setQuery($query);

        return (int) $db->loadResult();
    }
}
