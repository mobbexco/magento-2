import Toggle from 'react-toggle';

export default function Installment({
  uid,
  reference,
  name,
  description,
  advanced = true,
  active = false,
  index,
  list,
  setList,
}) {
  function setActive(event) {
    const newList = list;
    const plan    = event.target
    
    newList[index].active = plan.checked;

    const status = plan.dataset.advanced !== 'false'
      ? null 
      : {'reference': plan.name, 'active': plan.checked};

    setList(newList, status);
  } 

  return (
    <div className="installment">
      <div className="installment-desc">
        <div>
          <p>{name}</p>
          {advanced && <p className="advanced-text">Regla avanzada</p>}
        </div>
        <p className="small">{description}</p>
      </div> 
      <Toggle data-advanced={advanced} defaultChecked={active} icons={false} onChange={setActive} name={!advanced ? reference : uid} />
    </div>
  );
}
