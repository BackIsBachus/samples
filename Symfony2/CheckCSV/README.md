# CheckCSV

## Description
A Controller and a View that checks a CVS file provided against several criterea and display the errors

This Controller checks a CSV file provided by the user to see if it maches the correct structure in order to import its information into the application database.
This Controller also checks if the information provided looks correct considering a LDAP server and the application database.

The example situation here is we have an organization (company, school, association), they can go to some locations (identified by a unique code in the database different form the row ID) and they fill where they are going and when on a spreadsheet which is the converted into a CSV file.

For this example the spreadsheet columns will be the following:

* Person code
* Person email (since this person belongs to the organization we believe their email adress will end with @domain.tld to be sure we can contact them)
* Location code
* Start date (YYYY-MM-DD)
* End date (YYYY-MM-DD)

And therefore a line of the CSV file could be like

"124";"person@domain.tld";"47856";"2014-05-23";"2014-07-12"