<?php

/**
 * This file is part of the MusementGoogleSitemapBundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Musement\GoogleSitemapBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;
use App\Musement\GoogleSitemapBundle\Service\Generator;
use App\Musement\GoogleSitemapBundle\Service\Dumper;


class GoogleSitemapCommand extends Command
{

    private $twig;
    private $generator;
    private $params;

    public function __construct(Environment $twig, Generator $generator, Dumper $dumper, ParameterBagInterface $params)
    {
        $this->twig = $twig;
        $this->generator = $generator;
        $this->dumper = $dumper;
        $this->params = $params;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('musement:googlesitemap')
            ->setDescription('Google sitemap generator')
             ->addOption(
                'dir',
                'd',
                InputOption::VALUE_REQUIRED,
                'Directory to save sitemap to, for example /home/user/sitemap'
            )
            ->addOption(
                'recipients',
                'r',
                InputOption::VALUE_REQUIRED,
                'List of recipients to send sitemap to, for example "recipient1@example.com, recipient2@example.com"'
            )            
            ->addArgument(
                'locale',
                InputArgument::OPTIONAL,
                "Locale, default " . $this->params->get('default_locale')
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
            
        $localeArg = $input->getArgument('locale');
        if (!empty($localeArg)) {
                if(!in_array($localeArg, $this->params->get('supported_locales'))){
                        $output->writeln("{$localeArg} locale is not valid. Valid locales are " . implode(', ', $this->params->get('supported_locales')));
                        return 1;
                }
        } else {
            $output->writeln("No locale specified. Using default locale.");
            $localeArg = $this->params->get('default_locale');
        }
        
        if(!empty($input->getOption('recipients')) && !empty($input->getOption('dir'))){
            $output->writeln("--dir and --recipients options are  specified at the same time. Please select one of them for a single run. ");
            return 0;
        }

        $urls = $this->generator->getUrls($localeArg);        
        $text = $this->twig->render('GoogleSitemap/sitemap.xml.twig', array('urls' => $urls));

                
        if ($input->getOption('recipients')) {            
            $sendTo =  $input->getOption('recipients');
            $this->dumper->mail($sendTo, $localeArg, $text);
            $output->writeln("Sitemap has been sent by email to " . $sendTo);
            return 0;
        }
        
        $targetDir = $this->params->get('default_directory');
        if ($input->getOption('dir')) {
            $targetDir = $input->getOption('dir');
        }

        $filename = $this->dumper->save($targetDir, $localeArg, $text);
        $output->writeln("Sitemap has been successfuly saved to " . $filename);

        return 0;
    }
    
}
