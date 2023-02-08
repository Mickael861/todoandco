<?php

namespace tests\Controller\WebTestCase\user;

use App\Entity\User;
use App\TestsHelper\WebTestCaseHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCreateTest extends WebTestCase
{
    /**
     * @var KernelBrowser
     */
    private $client;

    /**
     * @var object|null
     */
    private $urlGenerator;

    /**
     * @var User
     */
    private $user;

    /**
     * @var User
     */
    private $admin;

    /**
     * @var WebTestCaseHelper
     */
    private $webTestCaseHelper;

    public function setUp(): void
    {
        $this->client = static::createClient();

        $this->urlGenerator = $this->client->getContainer()->get('router.default');

        $this->webTestCaseHelper = new WebTestCaseHelper($this->client, $this->urlGenerator);

        $this->user = $this->webTestCaseHelper->getEntity(User::class, 'findByUsername', 'user0');
        $this->admin = $this->webTestCaseHelper->getEntity(User::class, 'findByUsername', 'user1');
    }

    /**
     * verify that a non-logged-in user is redirected to login
     */
    public function testCreateActionUserNoLoggedRedirectLogin()
    {
        $this->webTestCaseHelper->getClientRequest('user_create');

        $this->client->followRedirect();

        $this->assertSelectorTextContains('h1', "S'identifier");
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    /**
     * Checks that a user does not have access to the user create space
     */
    public function testCreateActionUserLoggedAccesDenied()
    {
        $this->client->loginUser($this->user);

        $this->webTestCaseHelper->getClientRequest('user_create');

        $this->getExpectedExceptionMessage('Access Denied.');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * Checks that the user has access to the user part
     */
    public function testCreateActionAdminLogged()
    {
        $this->client->loginUser($this->admin);

        $this->webTestCaseHelper->getClientRequest('user_create');

        $this->assertSelectorTextContains('h1', "Créer un utilisateur");
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    /**
     * Test the create of a user successfull
     */
    public function testCreateActionCreateUserSuccessfull()
    {
        $this->client->loginUser($this->admin);

        $crawler = $this->webTestCaseHelper->getClientRequest('user_create');

        $this->webTestCaseHelper->submitForm($crawler, 'btn-form', [
            'user[username]' => 'username2',
            'user[email]'    => 'email2@outlook.fr',
            'user[roles]'    => 'ROLE_ADMIN',
            'user[user_password][first]' => "1234",
            "user[user_password][second]" => "1234"
        ]);

        $this->client->followRedirect();

        $task = $this->webTestCaseHelper->getEntity(User::class, 'findBy', [
            "username" => "username2",
            "email"    => "email2@outlook.fr"
        ]);

        $this->assertNotEmpty($task);
        $this->assertSame(['ROLE_ADMIN', 'ROLE_USER'], $task->getRoles());
        $this->assertSelectorTextContains(
            'div.alert.alert-success',
            "Superbe ! L'utilisateur a bien été ajouté."
        );
        $this->assertSelectorTextContains('h1', "Liste des utilisateurs");
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    /**
     * Test the create of a user error
     */
    public function testCreateActionCreateUserErrors()
    {
        $this->client->loginUser($this->admin);

        $crawler = $this->webTestCaseHelper->getClientRequest('user_create');

        $this->webTestCaseHelper->submitForm($crawler, 'btn-form', [
            'user[username]' => '',
            'user[email]'    => '',
            'user[roles]'    => 'ROLE_ADMIN',
            'user[user_password][first]' => "",
            "user[user_password][second]" => ""
        ]);

        $this->assertSelectorTextContains(
            "input#user_username ~ .invalid-feedback",
            "Vous devez saisir un nom d'utilisateur."
        );
        $this->assertSelectorTextContains(
            "input#user_email ~ .invalid-feedback",
            "Vous devez saisir une adresse email."
        );
        $this->assertSelectorTextContains(
            "input#user_user_password_first ~ .invalid-feedback",
            "Vous devez saisir un mot de passe."
        );
        $this->assertSelectorTextContains('h1', "Créer un utilisateur");
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    /**
     * Check that if the two passwords are different, there is an error during creation
     */
    public function testCreateActionCreateUserErrorPassowrdNotIdentical()
    {
        $this->client->loginUser($this->admin);

        $crawler = $this->webTestCaseHelper->getClientRequest('user_create');

        $this->webTestCaseHelper->submitForm($crawler, 'btn-form', [
            'user[username]' => 'username2',
            'user[email]'    => 'email2@outlook.fr',
            'user[roles]'    => 'ROLE_ADMIN',
            'user[user_password][first]' => "1234",
            "user[user_password][second]" => "4321"
        ]);

        $this->assertSelectorTextContains(
            "input#user_user_password_first ~ .invalid-feedback",
            "Les deux mots de passe doivent correspondre."
        );
        $this->assertSelectorTextContains('h1', "Créer un utilisateur");
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }
}
