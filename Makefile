index-data: index-data-es index-data-pg

index-data-es:
	/usr/local/bin/wof-es-index -s /usr/local/mapzen/whosonfirst-data/data -b

index-data-pg:
	/usr/local/bin/wof-spatial-index -c /usr/local/mapzen/whosonfirst.cfg -S spatial -s /usr/local/mapzen/whosonfirst-data/data/ -v
