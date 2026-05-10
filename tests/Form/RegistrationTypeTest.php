<?php

namespace App\Tests\Form;

use App\Entity\Client;
use App\Form\RegistrationType;
use Symfony\Component\Form\Test\TypeTestCase;

class RegistrationTypeTest extends TypeTestCase
{
    public function testSubmitValidData(): void
    {
        $formData = [
            'nom' => 'Doe',
            'prenom' => 'John',
            'email' => 'john.doe@example.com',
            'password' => [
                'first' => 'Password123!',
                'second' => 'Password123!',
            ],
            'telephone' => '12345678',
            'nationalite' => 'FR',
            'date_naissance' => '1990-01-01',
        ];

        $model = new Client();
        // The form uses date_naissance as a DateType with single_text widget
        // In the test, we pass the string, and the form will transform it into a DateTime object if correctly configured.
        
        $form = $this->factory->create(RegistrationType::class, $model);

        $expected = new Client();
        $expected->setNom('Doe')
            ->setPrenom('John')
            ->setEmail('john.doe@example.com')
            ->setPassword('Password123!')
            ->setTelephone('12345678')
            ->setNationalite('FR')
            ->setDateNaissance(new \DateTime('1990-01-01'));

        // submit the data to the form directly
        $form->submit($formData);

        // This check ensures there are no transformation failures
        $this->assertTrue($form->isSynchronized());

        // check that $model was modified as expected
        $this->assertEquals($expected->getNom(), $model->getNom());
        $this->assertEquals($expected->getPrenom(), $model->getPrenom());
        $this->assertEquals($expected->getEmail(), $model->getEmail());
        $this->assertEquals($expected->getPassword(), $model->getPassword());
        $this->assertEquals($expected->getTelephone(), $model->getTelephone());
        $this->assertEquals($expected->getNationalite(), $model->getNationalite());
        
        // Compare dates without time if necessary, but here we set them both to 1990-01-01
        $this->assertEquals($expected->getDateNaissance()->format('Y-m-d'), $model->getDateNaissance()->format('Y-m-d'));

        $view = $form->createView();
        $children = $view->children;

        foreach (array_keys($formData) as $key) {
            $this->assertArrayHasKey($key, $children);
        }
    }
}
