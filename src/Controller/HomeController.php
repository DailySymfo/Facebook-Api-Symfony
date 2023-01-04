<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Provider\Github;
use League\OAuth2\Client\Provider\Facebook;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController
{

   private $provider;

   private $github_provider;


   public function __construct()
   {
      $this->provider=new Facebook([
        'clientId'          => $_ENV['FCB_ID'],
        'clientSecret'      => $_ENV['FCB_SECRET'],
        'redirectUri'       => $_ENV['FCB_CALLBACK'],
        'graphApiVersion'   => 'v15.0',
    ]);


    $this->github_provider=new Github([
        'clientId'          => $_ENV['GITHUB_ID'],
        'clientSecret'      => $_ENV['GITHUB_SECRET'],
        'redirectUri'       => $_ENV['GITHUB_CALLBACK'],
    ]);


   }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/fcb-login', name: 'fcb_login')]
    public function fcbLogin(): Response
    {
         
        $helper_url=$this->provider->getAuthorizationUrl();

        return $this->redirect($helper_url);
    }


    #[Route('/fcb-callback', name: 'fcb_callback')]
    public function fcbCallBack(UserRepository $userDb, EntityManagerInterface $manager): Response
    {
       //Récupérer le token
       $token = $this->provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
        ]);

       try {
           //Récupérer les informations de l'utilisateur

           $user=$this->provider->getResourceOwner($token);

           $user=$user->toArray();

           $email=$user['email'];

           $nom=$user['name'];

           $picture=array($user['picture_url']);

           //Vérifier si l'utilisateur existe dans la base des données

           $user_exist=$userDb->findOneByEmail($email);

           if($user_exist)
           {
                $user_exist->setNom($nom)
                         ->setPictureUrl($picture);

                $manager->flush();


                return $this->render('show/show.html.twig', [
                    'nom'=>$nom,
                    'picture'=>$picture[0]
                ]);


           }

           else
           {
                $new_user=new User();

                $new_user->setNom($nom)
                      ->setEmail($email)
                      ->setPassword(sha1(str_shuffle('abscdop123390hHHH;:::OOOI')))
                      ->setPictureUrl($picture);
              
                $manager->persist($new_user);

                $manager->flush();


                return $this->render('show/show.html.twig', [
                    'nom'=>$nom,
                    'picture'=>$picture[0]
                ]);


           }


       } catch (\Throwable $th) {
        //throw $th;

          return $th->getMessage();
       }


    }


    #[Route('/github-login', name: 'github_login')]
    public function githubLogin(): Response
    {

        $options = [
            'scope' => ['user','user:email'] // On lui passe dans le scope les champs que nous souhaitons récupérer.
        ];


        $helper_url=$this->github_provider->getAuthorizationUrl($options);

        return $this->redirect($helper_url);



    }


     #[Route('/github-callback', name: 'github_callback')]
    public function githubCallBack(): Response
    {

        $token = $this->github_provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);



      
        try {
               //Récupérer les informations de l'utilisateur

               $user=$this->github_provider->getResourceOwner($token);

               $user=$user->toArray();

               $nom=$user['login'];

               $picture=$user['avatar_url'];

               //Vérifier si l'utilisateur existe dans la base des données. Si oui on fait la mise à jour de ses informations. Si non on va créer un nouvel utilisateur.

               return $this->render('show/show.html.twig', [
                'nom'=>$nom,
                'picture'=>$picture
               ]);


        } catch (\Throwable $th) {
            //throw $th;

            return $th->getMessage();
        }


    }
}
