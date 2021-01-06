--- src/gfanlib_polyhedralfan.cpp.orig	2017-06-20 14:47:37 UTC
+++ src/gfanlib_polyhedralfan.cpp
@@ -221,10 +221,10 @@ PolyhedralFan PolyhedralFan::fullComplex()const
 
   while(1)
     {
-      log2 debug<<"looping";
+      gfan_log2 debug<<"looping";
       bool doLoop=false;
       PolyhedralFan facets=ret.facetComplex();
-      log2 debug<<"number of facets"<<facets.size()<<"\n";
+      gfan_log2 debug<<"number of facets"<<facets.size()<<"\n";
       for(PolyhedralConeList::const_iterator i=facets.cones.begin();i!=facets.cones.end();i++)
         if(!ret.contains(*i))
           {
@@ -561,7 +561,7 @@ std::string PolyhedralFan::toString(int flags)const
         static int t;
 //        log1 fprintf(Stderr,"Adding faces of cone %i\n",t++);
       }
-//      log2 fprintf(Stderr,"Dim: %i\n",i->dimension());
+//      gfan_log2 fprintf(Stderr,"Dim: %i\n",i->dimension());
 
       addFacesToSymmetricComplex(symCom,*i,i->getHalfSpaces(),generatorsOfLinealitySpace);
     }
@@ -706,11 +706,11 @@ PolyhedralFan PolyhedralFan::readFan(string const &fil
 
     PolyhedralFan ret(n);
 
-    log2 cerr<< "Number of orbits to expand "<<cones.size()<<endl;
+    gfan_log2 cerr<< "Number of orbits to expand "<<cones.size()<<endl;
     for(int i=0;i<cones.size();i++)
       if(coneIndices==0 || coneIndices->count(i))
         {
-          log2 cerr<<"Expanding symmetries of cone"<<endl;
+          gfan_log2 cerr<<"Expanding symmetries of cone"<<endl;
           {
             IntegerVectorList coneRays;
             for(list<int>::const_iterator j=cones[i].begin();j!=cones[i].end();j++)
