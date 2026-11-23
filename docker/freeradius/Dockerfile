FROM freeradius/freeradius-server:3.2.10

COPY sql /etc/raddb/mods-available/sql
RUN ln -sf ../mods-available/sql /etc/raddb/mods-enabled/sql \
    && sed -i 's/^[[:space:]]*-sql$/sql/' /etc/raddb/sites-enabled/default /etc/raddb/sites-enabled/inner-tunnel \
    && sed -i 's/%{%{User-Password}:-%{Chap-Password}}//' /etc/raddb/mods-config/sql/main/postgresql/queries.conf
