<?php

public function checkAction() {

    // This the form used to get our CSV file from the user
    $form = $this->get('form.factory')->createNamedBuilder('import')
        ->add('submitFile', 'file', array('label' => 'CSV File '))
        ->getForm();

    $request = $this->getRequest();
    $session = $request->getSession();
    $em = $this->getDoctrine()->getManager();

    // We consider the request from the form 'import'
    if($request->request->has('import')) {

        $form->handleRequest($request);

        if($form->isValid()) {

            // We get the file submitted
            $file = $form->get('submitFile');

            // And we get its name to open and read it after
            $filename = $file->getData();

            // We try to open the file in Read mode
            if (($handle = fopen($filename, "r")) !== FALSE) {

                // This parameter controls the maximum length of a parameter (if 0 then the buffer is unlimited)
                $buffer = $this->container->getParameter('csv.buffer');

                /*
                    * We read the 1st line of the file which contains the model (so no useful data)
                    * The delimiter for the fields is ";" here
                    */
                $data = fgetcsv($handle, $buffer, ";");

                /*
                    * Number of expected fields for each line
					* Can be replaced by count($data) if we are sure that the medel line is correct
                    * But just in case: never trust user input
                    */
                $reference = $this->container->getParameter('csv.arguments');

                // We initialize the array used to store the lines which are incorrect
                $state = array();
                $line = 1;

                /*
                    * We iterate on all the lines of the CSV file (except the 1st one here)
					* If we encounter an empty line or the EOF we get FALSE and exit the while loop
                    */
                while (($data = fgetcsv($handle, $buffer, ";")) !==FALSE) {
                    $line++;

                    /*
                        * Local array that will store the store the state of the line while it is being checked
                        * We want the line number, if this line has a problem, the number of argument given and the list of the correct/incorrect fields
                        */
                    $local = array("line" => $line, "pb" => !(count($data) == $reference), "arguments" => count($data), "errors" => array());
                    /* Local array to store the correct/incorrect fields
                        * 0 = no error
                        * 1 = error
                        */
                    $errors = array();

                    // We check the number of field provided
                    if (count($data) == $reference) {

                        // We check the person's code number provided against the LDAP to see if they exist
                        $options = array(
                            'host' => $this->container->getParameter('ldap.host'),
                            'port' => $this->container->getParameter('ldap.port'),
                            'bindRequiresDn' => $this->container->getParameter('ldap.bindRequiresDn'),
                            'username' => $this->container->getParameter('ldap.username'),
                            'password' => $this->container->getParameter('ldap.password'),
                            'baseDn' => $this->container->getParameter('ldap.baseDn'));
                        $ldap = new Ldap($options);
                        $ldap->bind();
                        $result = $ldap->searchEntries('(personcode='.$data[0].')', 'ou=organization,dc=domain,dc=tld', Ldap::SEARCH_SCOPE_SUB);
                        $count = count($result);

                        // If we have 1 answer from our query then we are good to go
                        if ($count == 1) {
                            $errors[] = 0;
                        } else {
                            $errors[] = 1;
                            $local['pb']=true;
                        }

                        /* We check if the email adress is someting like person@domain.tld
                        * Where "domain.tld" is the domains used by the organization
                        * Since the format used by this organization is simple we can use the FILTER_VALIDATE_EMAIL first
                        * so that we can treat the obvious problems quickly
                        */
                        if(filter_var($data[1], FILTER_VALIDATE_EMAIL) != false){
                            $domain = explode("@", $data[1]);
                            $domain = $domain[1];

                            // Test for domain.tld
                            // strcasecmp is not case sensitive
                            if ((strcasecmp($domain, "domain.tld") == 0)) {
                                $errors[] = 0;
                            }
                            else {
                                $errors[] = 1;
                                $local['pb']=true;
                            }

                        }
                        else {
                            $errors[] = 1;
                            $local['pb']=true;
                        }


                        /*
                        * We are going to check if the location code provided exists in our database
                        */
                        $location = $em->getRepository('APPOrganizationBundle:Location')->findOneByCode($data[6]);
                        if ($location == null) {
                            $errors[] = 1;
                            $local['pb'] = true;
                        } else {
                            $errors[] = 0;
                        }


                        // We check the start date against the format YYYY-MM-DD and the it exists
                        if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$data[7]))
                        {
                            $date = explode("-", $data[7]);
                            if (checkdate($date[1], $date[2], $date[0])) {
                                $errors[] = 0;
                            } else {
                                $errors[] = 1;
                                $local['pb'] = true;
                            }
                        }
                        else{
                            $errors[] = 1;
                            $local['pb'] = true;
                        }

                        // We check the end date against the format YYYY-MM-DD and the it exists
                        // Could have done a function for this
                        if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$data[8]))
                        {
                            $date = explode("-", $data[8]);
                            if (checkdate($date[1], $date[2], $date[0])) {
                                $errors[] = 0;
                            } else {
                                $errors[] = 1;
                                $local['pb'] = true;
                            }
                        }
                        else{
                            $errors[] = 1;
                            $local['pb'] = true;
                        }

                        // Nom we put the errors in the local array describing the state of this line
                        $local['errors'] = $errors;

                    }

                    // And if there is a problem we add this array to the global return array
                    if ($local['pb']) {
                        $state[] = $local;
                    }

                }
                // And in the end we close the file
                fclose($handle);

                // If no error has been detected
                if (sizeof($state) == 0) {
                    $session->getFlashBag()->add('notice', 'The CSV file is correct');
                    return $this->redirect($this->generateUrl('app_user_st_check'));
                }
                else {
                    // If there was at least one we display a summary of the problems
                    $session->getFlashBag()->add('alert', 'The CSV file contains some errors');
                    return $this->render('APPUserBundle:Stay:check.html.twig', array(
                        'form' => $form->createView(),  'reference' => $this->container->getParameter('csv.arguments'), 'state' => $state));
                }

            }
            else {
                // Here we coudl'nt open the CSV file
                $session->getFlashBag()->add('alert', 'The CSV file could not be opened');
                return $this->redirect($this->generateUrl('app_user_st_check'));
            }
        }
    }

    return $this->render('APPUserBundle:Stay:check.html.twig', array(
        'form' => $form->createView(), 'reference' => $this->container->getParameter('csv.arguments'), 'state' => array()));
}
