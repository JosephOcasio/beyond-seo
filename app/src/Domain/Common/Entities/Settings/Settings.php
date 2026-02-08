<?php

declare(strict_types=1);

namespace App\Domain\Common\Entities\Settings;

use DDD\Domain\Base\Entities\EntitySet;

/**
 * @property Setting[] $elements;
 * @method Setting getByUniqueKey(string $uniqueKey)
 * @method Setting[] getElements()
 * @method Setting first()
 */
class Settings extends EntitySet
{
    public const SUPPORTED_SETTINGS = null;

    public function getSettingByType($type): ?Setting
    {
        return $this->getByUniqueKey($type);
    }

    /**
     * Merges foreach setting merges data from corresponding other settings entry if present, e.g. AdsSetting > AdsSetting.
     * If a setting is not present here but in otherSettings, it is newly created and merged (in order to not have a reference of the other setting but a copy instead).
     * @param Settings $otherSettings
     * @return void
     */
    public function mergeFromOtherSettings(Settings &$otherSettings): void
    {
        // first we look at all present settings and try to merge from other if existent
        foreach ($this->elements as $setting) {
            // If supported settings are set, we ignore the ones not supported
            if (static::SUPPORTED_SETTINGS !== null && !isset(static::SUPPORTED_SETTINGS[$setting::class])) {
                continue;
            }
            if ($otherSetting = $otherSettings->getByUniqueKey($setting->uniqueKey())) {
                if ($setting instanceof MergeableSetting) {
                    $setting->mergeFromOtherSetting($otherSetting);
                }
            }
        }
        // we now look at other and try to add non existent, as we already merged the existend ones
        foreach ($otherSettings->elements as $setting) {
            // If supported settings are set, we ignore the ones not supported
            if (static::SUPPORTED_SETTINGS !== null && !isset(static::SUPPORTED_SETTINGS[$setting::class])) {
                continue;
            }
            if (!$this->contains($setting)) {
                if (!($setting instanceof MergeableSetting)) {
                    continue;
                }
                $settingClass = $setting::class;
                /** @var MergeableSetting $newSetting */
                $newSetting = new $settingClass();
                $newSetting->mergeFromOtherSetting($setting);
                $this->add($newSetting);
            }
        }
    }
}