<?php

declare(strict_types=1);
namespace App\Controller\Admin;
use App\Application\Customer\Command\CreateCustomer\CreateCustomerHandler;
use App\Application\Customer\Command\CreateCustomer\CreateCustomerInput;
use App\Application\Customer\Command\UpdateCustomer\UpdateCustomerHandler;
use App\Application\Customer\Command\UpdateCustomer\UpdateCustomerInput;
use App\Application\Customer\Query\GetCustomer\{GetCustomerHandler, GetCustomerInput};
use App\Application\Customer\Query\SearchCustomers\SearchCustomersHandler;
use App\Application\Customer\Query\SearchCustomers\SearchCustomersInput;
use App\Application\Security\Permission;
use App\Domain\Customer\Customer;
use App\Domain\Customer\Repository\CustomerRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class CustomerController extends AbstractController
{
    #[Route('/app/customers', name: 'customers_index', methods: ['GET'])]
    public function index(Request $request, SearchCustomersHandler $search): Response
    { $this->denyAccessUnlessGranted(Permission::CUSTOMER_VIEW->value); $q=trim((string)$request->query->get('q','')); $customers=$q===''?[]:$search(new SearchCustomersInput($q,50)); return $this->render('customer/index.html.twig', compact('customers','q')); }

    #[Route('/app/customers/new', name: 'customers_new', methods: ['GET','POST'])]
    public function new(Request $request, CreateCustomerHandler $handler, CsrfTokenManagerInterface $csrf): Response
    { $this->denyAccessUnlessGranted(Permission::CUSTOMER_CREATE->value); $data=$this->data($request); if($request->isMethod('POST')){ $this->csrf($request,$csrf); try{$id=$handler(new CreateCustomerInput($data['name'],$data['phone']?:null,$data['note']?:null)); $this->addFlash('success','Customer created.'); return $this->redirectToRoute('customers_show',['id'=>$id]);}catch(\Throwable $e){$this->addFlash('error',$this->safe($e));}} $response=$this->render('customer/form.html.twig',['customer'=>null,'data'=>$data]); if($request->isMethod('POST')){$response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);} return $response; }

    #[Route('/app/customers/{id<\d+>}', name: 'customers_show', methods: ['GET'])]
    public function show(int $id, GetCustomerHandler $handler): Response
    {
        $this->denyAccessUnlessGranted(Permission::CUSTOMER_VIEW->value);
        $customer = $handler(new GetCustomerInput($id));
        if ($customer === null) {
            throw $this->createNotFoundException('Customer not found.');
        }

        return $this->render('customer/show.html.twig', ['customer' => $customer]);
    }

    #[Route('/app/customers/{id<\d+>}/edit', name: 'customers_edit', methods: ['GET','POST'])]
    public function edit(int $id, Request $request, CustomerRepositoryInterface $customers, UpdateCustomerHandler $handler, CsrfTokenManagerInterface $csrf): Response
    { $this->denyAccessUnlessGranted(Permission::CUSTOMER_EDIT->value); $customer=$customers->findById($id); if(!$customer instanceof Customer) throw $this->createNotFoundException('Customer not found.'); $data=$this->data($request,$customer); if($request->isMethod('POST')){$this->csrf($request,$csrf); try{$handler(new UpdateCustomerInput($id,$data['name'],$data['phone']?:null,$data['note']?:null));$this->addFlash('success','Customer updated.');return $this->redirectToRoute('customers_show',['id'=>$id]);}catch(\Throwable $e){$this->addFlash('error',$this->safe($e));}} $response=$this->render('customer/form.html.twig',['customer'=>$customer,'data'=>$data]); if($request->isMethod('POST')){$response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);} return $response; }

    private function data(Request $r, ?Customer $c=null):array{return ['name'=>trim((string)$r->request->get('name',$c?->getName()??'')),'phone'=>trim((string)$r->request->get('phone',$c?->getPhone()??'')),'note'=>trim((string)$r->request->get('note',$c?->getNote()??''))];}
    private function csrf(Request $r,CsrfTokenManagerInterface $m):void{if(!$m->isTokenValid(new CsrfToken('admin_customer_form',(string)$r->request->get('_token',''))))throw $this->createAccessDeniedException('Invalid CSRF token.');}
    private function safe(\Throwable $e):string{return $e instanceof \InvalidArgumentException||$e instanceof \DomainException?$e->getMessage():'Unable to complete the customer operation.';}
}
