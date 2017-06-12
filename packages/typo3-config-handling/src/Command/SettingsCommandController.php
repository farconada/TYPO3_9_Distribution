<?php
declare(strict_types=1);
namespace Helhum\Typo3ConfigHandling\Command;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Helmut Hummel <info@helhum.io>
 *  All rights reserved
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the text file GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use Helhum\TYPO3\ConfigHandling\ConfigCleaner;
use Helhum\TYPO3\ConfigHandling\ConfigDumper;
use Helhum\TYPO3\ConfigHandling\ConfigExtractor;
use Helhum\TYPO3\ConfigHandling\ConfigLoader;
use Helhum\TYPO3\ConfigHandling\RootConfig;
use Helhum\TYPO3\ConfigHandling\Xclass\ConfigurationManager;
use Helhum\Typo3Console\Mvc\Cli\CommandDispatcher;
use Helhum\Typo3Console\Mvc\Controller\CommandController;

class SettingsCommandController extends CommandController
{
    /**
     * @var ConfigurationManager
     */
    private $configurationManager;

    /**
     * @var ConfigCleaner
     */
    private $configCleaner;

    /**
     * @var ConfigDumper
     */
    private $configDumper;

    /**
     * @var ConfigExtractor
     */
    private $configExtractor;

    public function __construct(
        ConfigurationManager $configurationManager = null,
        ConfigCleaner $configCleaner = null,
        ConfigDumper $configDumper = null,
        ConfigExtractor $configExtractor = null
    ) {
        $this->configurationManager = $configurationManager ?: new ConfigurationManager();
        $this->configCleaner = $configCleaner ?: new ConfigCleaner();
        $this->configDumper = $configDumper ?: new ConfigDumper();
        $this->configExtractor = $configExtractor ?: new ConfigExtractor();
    }

    /**
     * Dump a (static) LocalConfiguration.php file
     *
     * The values are complied to respect all settings managed by the configuration loader.
     *
     * @param bool $noDev When set, only LocalConfiguration.php is written to contain the merged configuration ready for production
     * @throws \RuntimeException
     */
    public function dumpCommand($noDev = false)
    {
        if ($noDev) {
            $additionalConfigurationFile = $this->configurationManager->getAdditionalConfigurationFileLocation();
            if ($this->isAutoGenerated($additionalConfigurationFile)) {
                unlink($additionalConfigurationFile);
            }
            $configLoader = new ConfigLoader(RootConfig::getRootConfigFile($noDev));
            $config = $this->configCleaner->cleanConfig($configLoader->load());
        } else {
            $this->configurationManager->writeAdditionalConfiguration(
                [
                    '// Auto generated by helhum/typo3-config-handling',
                    '// Do not edit this file',
                    RootConfig::getInitConfigFileContent(),
                ]
            );
            $config = [];
        }
        $this->configDumper->dumpToFile(
            $config,
            $this->configurationManager->getLocalConfigurationFileLocation(),
            "Auto generated by helhum/typo3-config-handling\nDo not edit this file"
        );
    }

    /**
     * @return void
     */
    public function extractCommand()
    {
        $localConfigurationFile = $this->configurationManager->getLocalConfigurationFileLocation();
        if ($this->isAutoGenerated($localConfigurationFile)) {
            $this->outputLine('<info>LocalConfiguration.php does not exist or is auto generated. Nothing to extract.</info>');
            return;
        }
        $configuration = require $localConfigurationFile;
        $this->configExtractor->extractExtensionConfig($configuration);
        $this->configExtractor->extractMainConfig($configuration, $this->configurationManager->getDefaultConfiguration());
    }

    private function isAutoGenerated(string $file): bool
    {
        if (!file_exists($file)) {
            return false;
        }
        return false !== strpos(file_get_contents($file), 'Auto generated by helhum/typo3-config-handling');
    }
}
