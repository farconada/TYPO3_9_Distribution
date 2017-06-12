<?php
namespace Helhum\TYPO3\SetupHandling\Composer\InstallerScript;

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

use Composer\Script\Event as ScriptEvent;
use Helhum\ConfigLoader\Reader\RootConfigFileReader;
use Helhum\TYPO3\ConfigHandling\ConfigCleaner;
use Helhum\TYPO3\ConfigHandling\ConfigDumper;
use Helhum\TYPO3\ConfigHandling\ConfigExtractor;
use Helhum\TYPO3\ConfigHandling\ConfigLoader;
use Helhum\TYPO3\ConfigHandling\RootConfig;
use Helhum\Typo3Console\Mvc\Cli\CommandDispatcher;
use Helhum\Typo3ConsolePlugin\InstallerScriptInterface;
use Symfony\Component\Dotenv\Dotenv;
use TYPO3\CMS\Core\Configuration\ConfigurationManager;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use Typo3Console\PhpServer\Command\ServerCommandController;

class SetupConfiguration implements InstallerScriptInterface
{
    /**
     * @var string
     */
    private $dotEnvFile;

    /**
     * @var string
     */
    private $dotEnvDistFile;

    /**
     * @var string
     */
    private $dotEnvInstallFile;

    public function __construct()
    {
        $this->dotEnvFile = getenv('TYPO3_PATH_COMPOSER_ROOT') . '/.env';
        $this->dotEnvDistFile = getenv('TYPO3_PATH_COMPOSER_ROOT') . '/.env.dist';
        $this->dotEnvInstallFile = getenv('TYPO3_PATH_COMPOSER_ROOT') . '/.env.install';
    }

    /**
     * @param ScriptEvent $event
     * @return bool
     */
    public function shouldRun(ScriptEvent $event)
    {
        return getenv('TYPO3_IS_SET_UP');
    }

    /**
     * Call the TYPO3 setup
     *
     * @param ScriptEvent $event
     * @throws \RuntimeException
     * @return bool
     * @internal
     */
    public function run(ScriptEvent $event)
    {
        $io = $event->getIO();
        $io->writeError('');
        $io->writeError('<info>Setting up TYPO3 Configuration</info>');

        $dotEnvConfigContent = file_get_contents($this->dotEnvDistFile);
        $installFileValues = $this->getParsedEnvFileValues($this->dotEnvInstallFile);
        if (!empty($installFileValues)) {
            $io->writeError('<info>Please provide some required settings for your distribution:</info>');
        }
        foreach ($installFileValues as $envName => $envValue) {
            if (StringUtility::beginsWith($envName, 'TYPO3_INSTALL_PROMPT_')
                && !StringUtility::endsWith($envName, '_DEFAULT')
            ) {
                $defaultValue = $installFileValues[$envName . '_DEFAULT'] ?? null;
                do {
                    $answer = $event->getIO()->ask('<comment>' . $envValue . ($defaultValue ? sprintf(' (%s):', $defaultValue) : ':') . '</comment> ', $defaultValue);
                } while ($answer === null);
                $dotEnvConfigContent = str_replace('"${' . $envName . '}"', '\'' . $answer . '\'', $dotEnvConfigContent);
            }
        }
        $io->writeError('Generating .env file', true, $io::VERBOSE);
        $configurationManager = new ConfigurationManager();
        $configCleaner = new ConfigCleaner();
        // We are not interested in any settings that match the default configuration
        $settings = $configCleaner->cleanConfig($configurationManager->getLocalConfiguration(), $configurationManager->getDefaultConfiguration());
        foreach ($this->getParsedEnvFileValues($this->dotEnvDistFile) as $envName => $envValue) {
            if (StringUtility::beginsWith($envName, 'TYPO3__')) {
                try {
                    $configPath = str_replace(['TYPO3__', '__'], ['', '/'], $envName);
                    $value = ArrayUtility::getValueByPath($settings, $configPath);
                    $dotEnvConfigContent = str_replace($envName . '=""', $envName . '=\'' . $value . '\'', $dotEnvConfigContent);
                    $settings = ArrayUtility::removeByPath($settings, $configPath);
                } catch (\RuntimeException $e) {
                }
            }
        }
        file_put_contents($this->dotEnvFile, $dotEnvConfigContent);

        $io->writeError('Merging project settings', true, $io::VERBOSE);
        $configExtractor = new ConfigExtractor(
            new ConfigDumper(),
            $configCleaner,
            new ConfigLoader(RootConfig::getRootConfigFile(true))
        );
        $configExtractor->extractExtensionConfig($settings);
        $configExtractor->extractMainConfig($settings, $configurationManager->getDefaultConfiguration());
        $commandDispatcher = CommandDispatcher::createFromComposerRun($event);
        $commandDispatcher->executeCommand('settings:dump', ['--no-dev' => !$event->isDevMode()]);

        $io->writeError('');
        $io->writeError('<info>Your TYPO3 installation is now ready to use</info>');
        if (class_exists(ServerCommandController::class)) {
            $io->writeError('');
            $io->writeError(sprintf('Run <comment>%s server:run</comment> in your project root directory, to start the PHP builtin webserver.', substr($event->getComposer()->getConfig()->get('bin-dir') . '/typo3cms', strlen(getcwd()) + 1)));
        }
        $io->writeError('');
        return true;
    }

    /**
     * @param string $dotEnvFile
     * @return array
     */
    private function getParsedEnvFileValues($dotEnvFile)
    {
        if (!file_exists($dotEnvFile)) {
            return [];
        }
        return (new Dotenv())->parse(file_get_contents($dotEnvFile), $dotEnvFile);
    }
}
