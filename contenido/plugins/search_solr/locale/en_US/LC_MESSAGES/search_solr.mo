��          �   %   �      P     Q     `     l     y     �     �  
   �  
   �     �     �     �     �                    *     ;     L     [     i          �     �     �     �  b  �     %     4  "   @  6   c  2   �  !   �     �     
  *        F     Z     n  �   ~  ?   �  B   ?  E   �  G   �  �     3   �  l   .	  d   �	  4    
     5
     <
  :   B
                                                     	                                                       
                 CLIENT_OPTIONS DESCRIPTION DESCR_DELETE DESCR_HOSTNAME DESCR_LOGIN DESCR_PASSWORD DESCR_PATH DESCR_PORT DESCR_PROXY_HOST DESCR_PROXY_LOGIN DESCR_PROXY_PASSWORD DESCR_PROXY_PORT DESCR_REINDEX DESCR_RELOAD DESCR_SECURE DESCR_SSL_CAINFO DESCR_SSL_CAPATH DESCR_SSL_CERT DESCR_SSL_KEY DESCR_SSL_KEYPASSWORD DESCR_TIMEOUT DESCR_WT OPTION VALUE WARNING_INVALID_CLIENT_OPTIONS Project-Id-Version: CONTENIDO Solr
Report-Msgid-Bugs-To: 
PO-Revision-Date: 2023-03-05 17:32+0100
Last-Translator: Murat Pur� <murat@purc.de>
Language-Team: Marcus Gnaß <marcus.gnass@4fb.de>
Language: en
MIME-Version: 1.0
Content-Type: text/plain; charset=iso-8859-1
Content-Transfer-Encoding: 8bit
X-Poedit-KeywordsList: i18n
X-Generator: Poedit 3.2.2
 client options description Delete articles of current client. The hostname for the Solr server. <b>w/o protocol!</b> The username used for HTTP Authentication, if any. The HTTP Authentication password. The path to the Solr core. The port number. The hostname for the proxy server, if any. The proxy username. The proxy password. The proxy port. Reindex articles of current client. Articles that are offline, not searchable or hidden by protected categories will be skipped. Reload the core according to its configuration file schema.xml. Boolean value indicating whether or not to connect in secure mode. Name of file holding one or more CA certificates to verify peer with. Name of directory holding multiple CA certificates to verify peer with. File name to a PEM-formatted file containing the private key + private certificate (concatenated in that order). Please note the if the ssl_cert file only contains the private certificate, you have to specify a separate ssl_key file. File name to a PEM-formatted private key file only. Password for private key. The ssl_keypassword option is required if the ssl_cert or ssl_key options are set. This is maximum time in seconds allowed for the http data transfer operation. Default is 30 seconds. The name of the response writer e.g. xml, phpnative. option value Article could not be indexed cause Solr is not configured. 