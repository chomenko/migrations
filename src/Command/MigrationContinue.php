<?php
/**
 * Author: Mykola Chomenko
 * Email: mykola.chomenko@dipcom.cz
 */
namespace Chomenko\Migrations\Command;

use Doctrine\DBAL\Migrations\Tools\Console\Command\AbstractCommand;
use Kdyby\Console\Application;
use Kdyby\Doctrine\EntityManager;
use Kdyby\Doctrine\Tools\CacheCleaner;
use Nette\DI\Container;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationContinue extends AbstractCommand
{

	/**
	 * @var Container @inject
	 */
	public $container;

	/**
	 * @var CacheCleaner @inject
	 */
	public $cacheCleaner;

	protected function configure()
	{
		$this->setName('migrations:continue')
			->setDescription("Update schema and data.")
			->addOption(
				'force-scheme',
				null,
				InputOption::VALUE_NONE,
				'It updates the schema even if the migration is not created. migrations:continue --force-scheme'
			);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @throws \Exception
	 */
	public function execute(InputInterface $input, OutputInterface $output)
	{
		$configuration = $this->getMigrationConfiguration($input, $output);

		$configuration->setName("Update scheme and data");
		$this->outputHeader($configuration, $output);

		$availableMigrations = count($configuration->getAvailableVersions());
		$application = $this->container->getByType(Application::class);


		$question    = 'WARNING! You are about to execute a database migration'
			. ' that could result in schema changes and data loss.'
			. ' Are you sure you wish to continue? (y/n)';
		if (!$this->canExecute($question, $input, $output)) {
			return 0;
		}


		if ($availableMigrations > 0) {
			$command = $application->find("migration:migrate");
			$arguments = ["--allow-no-migration" => TRUE, "--no-interaction" => TRUE];
			$greetInput = new ArrayInput($arguments);
			$greetInput->setInteractive(FALSE);
			$command->run($greetInput, $output);
		} else if($input->getOption('force-scheme')){
			$command = $application->find("orm:schema-tool:update");
			$command->cacheCleaner = $this->cacheCleaner;
			$arguments = ["--force" => TRUE, "--no-interaction" => TRUE];
			$greetInput = new ArrayInput($arguments);
			$greetInput->setInteractive(FALSE);
			$command->run($greetInput, $output);
		}

		$command = $application->find("migrations:data:migrate");
		$arguments = ["--no-interaction" => TRUE];
		$greetInput = new ArrayInput($arguments);
		$greetInput->setInteractive(FALSE);
		$command->run($greetInput, $output);
	}

	/**
	 * @param string $question
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return bool
	 */
	private function canExecute($question, InputInterface $input, OutputInterface $output)
	{
		if ($input->isInteractive() && ! $this->askConfirmation($question, $input, $output)) {
			return false;
		}

		return true;
	}

}
