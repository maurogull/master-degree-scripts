# master-degree-scripts
Scripts implemented for my master's degree thesis work

I worked with MediaWiki's source code, which is written in PHP, so using PHP for these scripts was a natural choice in order to take advantage of Reflection (and a lot of regex).

Our work can be summarized as follows:
* Inspect 2871 Git commits (~114 KLOC involved) :(
* Organize commits in a relational database
* Build tools to calculate metrics from database and source code :(
* Logistic Regression: predict defective classes with the class' metrics

In brief, the findings were:
* **Quantity and size of changes** made to a class' code predicts its future defects
* Change metrics are better predictors than static ones


## Complete work

This is a companion for the main document available at http://sedici.unlp.edu.ar/handle/10915/93237

"Predicción de defectos en un lenguaje dinámicamente tipado usando métricas estáticas y de cambio"  
(_Defect prediction in a dynamic-type language using static and change metrics_)  
Universidad Nacional de La Plata, 2020


