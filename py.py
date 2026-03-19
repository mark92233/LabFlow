import csv

# The data generated for you
data = [
    {"id": 38271, "name": "Antonio Luna Silverio", "email": "antonio.silverio@example.com"},
    {"id": 19203, "name": "Bernadette Sembrano Vizcarra", "email": "b.vizcarra@example.com"},
    {"id": 74829, "name": "Crisanto Evangelista", "email": "crisanto.e@example.com"},
    {"id": 55032, "name": "Dahlia Flores Kusaka", "email": "dahlia.kusaka@example.com"},
    {"id": 44921, "name": "Emilio Jacinto Rivera", "email": "emilio.rivera@example.com"},
    {"id": 88302, "name": "Fe Del Mundo Solis", "email": "fe.solis@example.com"},
    {"id": 12039, "name": "Gregorio del Pilar Cruz", "email": "gregorio.cruz@example.com"},
    {"id": 66293, "name": "Hilda Koronel Pineda", "email": "hilda.pineda@example.com"},
    {"id": 31048, "name": "Isko Moreno Domagoso", "email": "isko.domagoso@example.com"},
    {"id": 99281, "name": "Jovito Salonga Recto", "email": "jovito.recto@example.com"},
    {"id": 47382, "name": "Karylle Tatlonghari Padua", "email": "k.padua@example.com"},
    {"id": 22019, "name": "Leandro Locsin Vinuya", "email": "leandro.vinuya@example.com"},
    {"id": 85732, "name": "Melchora Aquino Ramos", "email": "melchora.ramos@example.com"},
    {"id": 56102, "name": "Nora Aunor Villamayor", "email": "nora.v@example.com"},
    {"id": 77481, "name": "Olympia Guanzon", "email": "olympia.guanzon@example.com"},
    {"id": 10293, "name": "Paciano Rizal Mercado", "email": "paciano.mercado@example.com"},
    {"id": 63920, "name": "Quintin Paredes", "email": "quintin.paredes@example.com"},
    {"id": 44831, "name": "Regine Velasquez Alcasid", "email": "regine.alcasid@example.com"},
    {"id": 29304, "name": "Sergio Osmeña Sr. Chiong", "email": "sergio.chiong@example.com"},
    {"id": 81023, "name": "Trinidad Tecson", "email": "trinidad.tecson@example.com"},
    {"id": 52938, "name": "Ulysses S. Grant Belmonte", "email": "u.belmonte@example.com"},
    {"id": 33948, "name": "Vilma Santos Recto", "email": "vilma.recto@example.com"},
    {"id": 71029, "name": "Waldo Perfecto", "email": "waldo.perfecto@example.com"},
    {"id": 15482, "name": "Ximena Castillo", "email": "ximena.castillo@example.com"},
    {"id": 90231, "name": "Ysabella Roxas", "email": "ysabella.roxas@example.com"},
    {"id": 46281, "name": "Zosimo Romulo", "email": "zosimo.romulo@example.com"},
    {"id": 28394, "name": "Aga Muhlach Cheng", "email": "aga.cheng@example.com"},
    {"id": 62930, "name": "Bea Alonzo Phol", "email": "bea.phol@example.com"},
    {"id": 11203, "name": "Cesar Montano Manhilot", "email": "cesar.manhilot@example.com"},
    {"id": 88372, "name": "Diether Ocampo Pascual", "email": "diether.pascual@example.com"}
]

filename = "filipino_data1.csv"
fields = ["id", "name", "email"]

# Writing to the csv file
with open(filename, 'w', newline='', encoding='utf-8') as csvfile:
    writer = csv.DictWriter(csvfile, fieldnames=fields)
    
    # Write the header
    writer.writeheader()
    
    # Write the rows
    writer.writerows(data)

print(f"Successfully created {filename}!")