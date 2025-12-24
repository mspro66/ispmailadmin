ISPmail Admin
-------------

ISPmail Admin is a web tool writen in php by Ole Jungclaussen based on database strutture,
defined and explained in **ISPmail Guide** maintainded by Christoph Haas on 
[workaround.org web site](https://workaround.org/ispmail-trixie/).

In this repository there is a revisited enhanced version made by [me](https://github.com/mspro66).

### What's in this revised version

- To differentiate this version from the original one, the color theme has been changed. We also rebuilt
  the menu bar.
- The columns of the data tables have been rearranged to improve their display and operation
- User mailbox, alias, and redirect management now depend on the currently selected working domain.
    - The domain to work on must be selected using the new "select" element located between the menu bar and the title of the activated section;
    - In the user creation area, the "select" element of the domains has been removed;
    - In the redirect creation area, the domain's "select" element has been removed.
- New alias management
    - aliases are now owned by users and cannot be shared with redirects;
    - user aliases can now have more than one destination;
    - On the alias list grid there is a new button that allows you to easily insert a new destination into an existing alias.
- Redirects are now domain-owned
    - You can no longer enter a new redirect if it already exists an alias of a mailbox or if there is a mailbox with the same address;
    - On the redirect list grid there is a new button that allows you to easily insert a new destination into an existing redirect;
- Implemented various checks on user-entered addresses and database consistency.
- Improved and added various explanatory error messages
  
### Installation 
1) follow setup instructions on the [original README](doc/README_original.md) by O.J.
2) Open a sql console and run the following statements:
   - add new field on virtual_aliases table:
       ```sql
       alter table virtual_aliases
       ADD COLUMN mailbox_id int(11),
       add constraint fk_mailbox foreign key (mailbox_id) references virtual_users (id);
       ```
   - create global domain users who will own the redirects
       ```sql
       replace into virtual_users (domain_id, email, password)
       SELECT id, concat("@", name), "~"
       FROM virtual_domains;
       ```
   - link aliases to their inbox
       ```sql
       update virtual_aliases
       set mailbox_id = (select mb.id from virtual_users as mb where mb.email = destination);
       ```
   - If the previous command was run without errors, link the domain redirects to their relevant global user    
       ```sql
       update virtual_aliases as m
       set mailbox_id = (
         select u.id
         from virtual_users as u
         where u.domain_id = m.domain_id and u.email = concat("@", (select d.name from virtual_domains as d where d.id = m.domain_id))
       )
       where mailbox_id is null;
       ```

