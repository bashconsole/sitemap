<?php
/**
 * This file is part of the MusementGoogleSitemapBundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace App\Musement\GoogleSitemapBundle\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;
use Psr\Log\LoggerInterface;

/*
* Dumps sitemap to file or sends it by email 
*/
class Dumper
{
    /**
     * Path to folder where temporary files will be created
     * @var string
     */
    private $tmpFolder;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var string
     */
    protected $sitemapFilePrefix;
    
    private $twig;
    private $mailer;
    private $logger;
    private $params;

    
    public function __construct(Filesystem $filesystem, ParameterBagInterface $params,
        Environment $twig,  \Swift_Mailer $mailer, LoggerInterface $logger) {
        
        $this->filesystem = $filesystem;
        $this->sitemapFilePrefix = 'sitemap';
        if(!empty($params->get('sitemap_prefix'))){
            $this->sitemapFilePrefix = $params->get('sitemap_prefix');
        }
        $this->params = $params; 
        $this->twig = $twig;
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    /**
     * creates sitemap file in temporary folder
     *
     * @param string $locale Language locale
     *
     * @param string $content sitemap file content
     *
     * @return string $tmpFullFileName Full file name in temporary folder
     */
    protected function dump($locale, $content)
    {
        $tmpFolder = $this->getTempFolder();
        $tmpFullFileName = $tmpFolder . '/' . $this->getFileName($locale);
        file_put_contents($tmpFullFileName, $content);
        
        return $tmpFullFileName;
    }

    /**
     * saves sitemap file in folder, removes temporary folder after that
     *
     * @param string $targetDir Directory to save sitemap file to
     *
     * @param string $locale Language locale
     *
     * @param string $content sitemap file content
     *
     * @return string returns full filename to saved sitemap
     */
    public function save($targetDir, $locale, $content)
    {
        $this->dump($locale, $content);
        $this->activate($targetDir);
        
        return $targetDir . "/" . $this->getFileName($locale);
    }

    /**
     * send sitemap file by email
     *
     * @param string $mailTo comma separated list of recipients
     *
     * @param string $locale Language locale
     *
     * @param string $content sitemap file content
     *
     * @return boolean returns true in case success, false otherwise
     */
    public function mail($mailTo, $locale, $content)
    {
    
        $fullFileName = $this->dump($locale, $content);
        $recipients = explode(",", $mailTo);
        //trim spaces to  comply with RFC 2822
        foreach($recipients as $k => $v){
                $recipients[$k] = trim($v);
        }
    
        $name = 'test';
        $message = (new \Swift_Message('MUSEMENT.COM sitemap for ' . $this->getFileName($locale)))
        ->setFrom($this->params->get('email_from'))
        ->setTo($recipients)
        ->setBody(
            $this->twig->render(
                // templates/emails/test.html.twig
                'emails/test.html.twig',
                ['filename' => $this->getFileName($locale)]
            ),
            'text/html'
        )
        ->addPart(
            $this->twig->render(
                // templates/emails/test.txt.twig
                'emails/test.txt.twig',
                ['filename' => $this->getFileName($locale)]
            ),
            'text/plain'
        )
        ->attach(\Swift_Attachment::fromPath($fullFileName)->setFilename($this->getFileName($locale)))
        ;

        
        try {
            $this->mailer->send($message);
            $this->logger->info('Sitemap sent by email successfuly');
        } catch (\Swift_TransportException $e) {
            $this->logger->critical('Send failed - deleting spooled message');
            return false;
        }
    
        return true;
    }
    
    
    private function getTempFolder()
    {
        if(empty($this->tmpFolder)){
                $this->tmpFolder = sys_get_temp_dir() . '/musement-' . uniqid();
                $this->filesystem->mkdir($this->tmpFolder);
        }
        
        return $this->tmpFolder;
    }

    /**
     * deletes temporary folder
     */
    private function cleanup()
    {
        $this->logger->info('Removing temporary folder ' . $this->tmpFolder);
        if(!empty($this->tmpFolder)){
                $this->filesystem->remove($this->tmpFolder);
        }
        $this->tmpFolder = null;
    }

    /**
     * send sitemap file by email
     *
     * @param string $targetDir target directory
     */    
    private function activate($targetDir)
    {
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        if (!is_writable($targetDir)) {
            $this->cleanup();
            throw new \RuntimeException(
                sprintf('Can\'t move sitemap to "%s" - directory is not writeable', $targetDir)
            );
        }

        $this->filesystem->mirror($this->tmpFolder, $targetDir, null, ['override' => true]);
        $this->cleanup();        
    }
    
    public function getFileName($locale)
    {
        return $this->sitemapFilePrefix . '_' . $locale . '.xml';
    }

}
