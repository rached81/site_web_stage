<?php

namespace App\Controller\Back;

use App\Entity\Article;
use App\Entity\Content;
use App\Entity\Language;
use App\Form\ContentType;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\ContentRepository;
use App\Repository\LanguageRepository;
use App\Repository\MenuRepository;
use App\Repository\ScopeRepository;
use Exception;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security as Sec;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/back/{_locale}/content", requirements={  "_locale": "fr|ar|en"   })
 *
 * @Sec("is_granted('IS_AUTHENTICATED_FULLY')")
 */
class ContentController extends AbstractController
{
    private $security;
    private $articleRepository;
    private $contentRepository;
    private $languageRepository;
    private $categoryRepository;
    private $translator;
    private $session;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     *  constructor.
     */
    public function __construct(LoggerInterface     $logger,
                                Security            $security,
                                ContentRepository   $contentRepository,
                                LanguageRepository  $languageRepository,
                                CategoryRepository  $categoryRepository,
                                ArticleRepository   $articleRepository,
                                TranslatorInterface $translator,
                                SessionInterface    $session)
    {
        // Avoid calling getUser() in the constructor: auth may not
        // be complete yet. Instead, store the entire Security object.
        $this->security = $security;
        $this->articleRepository = $articleRepository;
        $this->contentRepository = $contentRepository;
        $this->languageRepository = $languageRepository;
        $this->categoryRepository = $categoryRepository;
        $this->translator = $translator;
        $this->logger = $logger;
        $this->session = $session;

    }

    /**
     * @Route("/", name="app_content_index", methods={"GET"})
     */
    public function index(ContentRepository $contentRepository, ScopeRepository $scopeRepository, LanguageRepository $languageRepository, Request $request): Response
    {
        // dump($this->security->getUser());
        $scopeId = $request->get('scope');
        if(is_null($scopeId)){
            return $this->redirectToRoute('app_contact_index', [], Response::HTTP_SEE_OTHER);
        }
        $scope = $scopeRepository->find($scopeId);
//        dump($scope);
        $this->session->set('current_scope', $scope);
        $loc_url = $request->get('_locale');
        $loc_url = $request->getLocale();
        $lang_from_url = $languageRepository->findOneByAlias($loc_url);
        $listContent = $contentRepository->findBy(['language' => $lang_from_url, 'scope' => $scope], ['created_at' => 'DESC']);
        $this->logger->info('USER : ' . $loc_url . ' : display article list');
        return $this->render('back/content/index.html.twig', [
            'contents' => $listContent,
            'scope' => $scopeId
        ]);
    }

    /**
     * @Route("/new", name="app_content_new", methods={"GET", "POST"})
     */
    public function new(Request $request, SluggerInterface $slugger, LanguageRepository $languageRepository, ContentRepository $contentRepository, CategoryRepository $categoryRepository, ArticleRepository $articleRepository): Response
    {
        $content = new Content();
        $form = $this->createForm(ContentType::class, $content);
        $form->handleRequest($request);
        $loc_url = $request->get('_locale');
        $lang_from_url = $languageRepository->findOneByAlias($loc_url);
        $content->setLanguage($lang_from_url);
        $scope = $request->get('scope');
//        $scope == 1?$aliasCat = 'NEWS':$aliasCat = 'SIMPLE';
        switch ($scope) {
            case 1:
                $aliasCat = 'SIMPLE';
                break;
            case 2:
                $aliasCat = 'NEWS';
                break;
            case 4:
                $aliasCat = 'AREA_JOURNALIST';
                break;
            case 5:
                $aliasCat = 'FORM';
                break;
            default:
                $aliasCat = 'SIMPLE';
                break;
        }
       // dd($aliasCat);
        if ($form->isSubmitted() && $form->isValid()) {
            $article = new Article();
            $cat = $categoryRepository->findOneByAlias($aliasCat);
            $article->setCategory($cat);
            $article->setNum(uniqid());
            $articleRepository->add($article);
            $content->setArticle($article);
            $uploadedFile = $form['picture']->getData();

            if ($uploadedFile) {
                $destination = $this->getParameter('kernel.project_dir') . '/public/uploads';
                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->guessExtension();
                $uploadedFile->move(
                    $destination,
                    $newFilename
                );
                $content->setPicture($newFilename);
            }
            $contentRepository->add($content);

            return $this->redirectToRoute('app_content_index', ['scope' => $content->getScope()->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('back/content/new.html.twig', [
            'content' => $content,
            'form' => $form,
//            'scope' => 1

        ]);
    }

    public function translateContentArticle(Request $request, $id, LanguageRepository $languageRepository, ContentRepository $contentRepository, CategoryRepository $categoryRepository, ArticleRepository $articleRepository): Response
    {
        $content = new Content();
        $form = $this->createForm(ContentType::class, $content);
        $form->handleRequest($request);
        $loc_url = $request->get('_locale');
        $objlang_from_url = $languageRepository->findOneByAlias($loc_url);
        $content->setLanguage($objlang_from_url);
        if ($form->isSubmitted() && $form->isValid()) {
            $article = $contentRepository->find($id)->getArticle();
            $articleRepository->add($article);
            $content->setArticle($article);
//            dump($content);
            $contentRepository->add($content);
            return $this->redirectToRoute('app_content_index', ['scope' => $content->getScope()->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('back/content/new.html.twig', [
            'content' => $content,
            'form' => $form,
            //'scope' => Scope::ALL
        ]);
    }


    public function newContentArticle(Request $request, $id, LanguageRepository $languageRepository, ContentRepository $contentRepository, CategoryRepository $categoryRepository, ArticleRepository $articleRepository): Response
    {
        $content = new Content();
        $form = $this->createForm(ContentType::class, $content);
        $form->handleRequest($request);
        $loc_url = $request->get('_locale');
        $objlang_from_url = $languageRepository->findOneByAlias($loc_url);
        $content->setLanguage($objlang_from_url);

        if ($form->isSubmitted() && $form->isValid()) {

            $article = $contentRepository->find($id)->getArticle();
            $articleRepository->add($article);
            $content->setArticle($article);
            $contentRepository->add($content);
            return $this->redirectToRoute('app_content_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('back/content/new.html.twig', [
            'content' => $content,
            'form' => $form,
            ////'scope' => Scope::ALL
        ]);
    }

    /**
     * @Route("/{id}", name="app_content_show", methods={"GET"} )
     */
    public function show(Content $content, Request $request): Response
    {
        $loc_url = $request->get('_locale') ?? 'fr';
        $objlang_from_url = $this->languageRepository->findOneByAlias($loc_url);
        //dd($objlang_from_url);
        $article = $content->getArticle();
        $content = $this->validContent($objlang_from_url, $article);
        $loc_url = $request->get('_locale');
        $currentLang = $objlang_from_url->getName();
        return $this->render('back/content/show.html.twig', [
            'content' => $content,
            'language' => $currentLang,
//            //'scope' => Scope::ALL
        ]);
    }

    /**
     * @Route("/{id}/edit/{article_id}", name="app_content_edit", methods={"GET", "POST"})
     */
    public function edit(Request $request, SluggerInterface $slugger, $article_id, Content $content, LanguageRepository $languageRepository, ContentRepository $contentRepository, TranslatorInterface $translator): Response
    {

        $loc_url = $request->get('_locale');
        $objlang_from_url = $languageRepository->findOneByAlias($loc_url);
        $article = $content->getArticle();
        $action = $this->typeOfAction($objlang_from_url, $article);
        if ($action == 'edit') {
            $content = $this->validContent($objlang_from_url, $article);
            $form = $this->createForm(ContentType::class, $content);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {

                $uploadedFile = $form['picture']->getData();
                if ($uploadedFile) {
                    $destination = $this->getParameter('kernel.project_dir') . '/public/uploads';
                    $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->guessExtension();
                    $uploadedFile->move(
                        $destination,
                        $newFilename
                    );
                    $content->setPicture($newFilename);
                }
                $this->contentRepository->add($content);
                $message = $this->translator->trans("l article est enregistrer avec succès");
                $this->addFlash("message", $message);
                //  $this->addFlash("message", "l article est enregistrer avec succès");
                return $this->redirectToRoute('app_content_show', ['id' => $content->getId()]);
            }
            $urlPicture = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $content->getPicture();
            return $this->renderForm('back/content/edit.html.twig', [
                'content' => $content,
                'form' => $form,
                'url_picture' => $urlPicture
                //'scope' => Scope::ALL
            ]);

        }
        if ($action === 'new') {
            $content = new Content();
            $form = $this->createForm(ContentType::class, $content);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $content->setArticle($article);
                $content->setLanguage($objlang_from_url);
                $contentRepository->add($content);
                return $this->redirectToRoute('app_content_index', ['scope' => $content->getScope()->getId()], Response::HTTP_SEE_OTHER);
            }
            return $this->renderForm('back/content/new.html.twig', [
                'content' => $content,
                'form' => $form,
                //'scope' => Scope::ALL
            ]);

        }
    }

    /**
     * @Route("/{id}", name="app_content_delete", methods={"POST"})
     */
    public function delete(Request $request, Content $content, ContentRepository $contentRepository, MenuRepository $menuRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $content->getId(), $request->request->get('_token'))) {
            $menus = $menuRepository->findBy(['content' => $content]);
                foreach ($menus as $menu) {
                    $menuRepository->remove($menu);
                }

            $contentRepository->remove($content);
        }

        return $this->redirectToRoute('app_content_index', ['scope' => $content->getScope()->getId()], Response::HTTP_SEE_OTHER);
    }


    public function firstNWord(string $sentence, int $rankWord)
    {
        $str = trim(strip_tags($sentence));

        return implode(' ', array_slice(explode(' ', $str), 0, $rankWord)) . " ...";

    }

    public function typeOfAction(Language $lang, Article $article)
    {

        $content = $this->contentRepository->findBy(['language' => $lang, 'article' => $article]);

        if (count($content) > 0) {
            return 'edit';

        } else {
            return 'new';
        }


    }

    public function validContent(Language $lang, Article $article)
    {

        $content = $this->contentRepository->findBy(['language' => $lang, 'article' => $article]);
        if (count($content) === 0) {
            return "";
        }
        if (count($content) == 1) {
            return $content[0];

        } else {
            throw new Exception('content duplicated ' . count($content));
        }


    }
}
